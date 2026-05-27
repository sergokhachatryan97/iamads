package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"
)

func main() {
	// Structured logging
	slog.SetDefault(slog.New(slog.NewTextHandler(os.Stderr, &slog.HandlerOptions{Level: slog.LevelDebug})))

	botToken := envOr("TG_BOT_TOKEN", "8675161582:AAENuaX8sM50-OC8QLYkhHRkrqp01FuNgIM")
	port := envOr("TG_INSPECTOR_PORT", "8090")
	sessionsDir := envOr("TG_SESSIONS_DIR", "./sessions_ready")
	// CLI mode — single link inspection
	if len(os.Args) >= 2 && os.Args[1] != "serve" {
		runCLI(botToken, os.Args[1])
		return
	}

	// ── Start service ──────────────────────────────────────
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// 1) Bot client — persistent MTProto connection
	bot := NewBotClient(botToken)
	go func() {
		if err := bot.Start(ctx); err != nil {
			slog.Error("bot client stopped", "error", err)
		}
	}()

	// Wait for bot to be ready (max 10s)
	waitCtx, waitCancel := context.WithTimeout(ctx, 10*time.Second)
	if err := bot.WaitReady(waitCtx); err != nil {
		slog.Warn("bot client not ready, will use fallbacks", "error", err)
	}
	waitCancel()

	// 2) User pool — load session files
	pool := NewUserPool()
	if err := pool.LoadSessions(ctx, sessionsDir); err != nil {
		slog.Error("failed to load user sessions", "error", err)
	}

	// 3) Inspector
	inspector := NewInspector(bot, pool)

	// ── HTTP Server ────────────────────────────────────────
	mux := http.NewServeMux()

	// POST /inspect
	mux.HandleFunc("/inspect", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			writeJSON(w, 405, map[string]any{"ok": false, "error": "POST only"})
			return
		}

		var req InspectRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			writeJSON(w, 400, map[string]any{"ok": false, "error": "invalid json"})
			return
		}
		if req.Link == "" {
			writeJSON(w, 400, map[string]any{"ok": false, "error": "link required"})
			return
		}

		reqCtx, reqCancel := context.WithTimeout(r.Context(), 20*time.Second)
		defer reqCancel()

		result := inspector.Inspect(reqCtx, req)
		writeJSON(w, 200, result)
	})

	// GET /health
	mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		writeJSON(w, 200, map[string]any{
			"status":          "ok",
			"bot_ready":       bot.IsReady(),
			"user_accounts":   pool.TotalCount(),
			"user_available":  pool.AvailableCount(),
		})
	})

	srv := &http.Server{
		Addr:         ":" + port,
		Handler:      mux,
		ReadTimeout:  5 * time.Second,
		WriteTimeout: 25 * time.Second,
	}

	// Graceful shutdown
	go func() {
		sig := make(chan os.Signal, 1)
		signal.Notify(sig, syscall.SIGINT, syscall.SIGTERM)
		<-sig
		slog.Info("shutting down...")
		shutCtx, shutCancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer shutCancel()
		srv.Shutdown(shutCtx)
		bot.Stop()
		cancel()
	}()

	slog.Info("TelegramInspector started",
		"port", port,
		"bot_token", botToken[:10]+"...",
		"sessions_dir", sessionsDir,
		"user_accounts", pool.TotalCount(),
	)
	slog.Info("endpoints", "inspect", "POST /inspect", "health", "GET /health")

	if err := srv.ListenAndServe(); err != http.ErrServerClosed {
		slog.Error("server error", "error", err)
		os.Exit(1)
	}
}

func runCLI(botToken, link string) {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	bot := NewBotClient(botToken)
	go bot.Start(ctx)

	waitCtx, waitCancel := context.WithTimeout(ctx, 10*time.Second)
	bot.WaitReady(waitCtx)
	waitCancel()

	pool := NewUserPool()
	inspector := NewInspector(bot, pool)

	result := inspector.Inspect(ctx, InspectRequest{Link: link})
	out, _ := json.MarshalIndent(result, "", "  ")
	fmt.Println(string(out))

	if !result.OK {
		os.Exit(1)
	}
}

func writeJSON(w http.ResponseWriter, status int, data any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
