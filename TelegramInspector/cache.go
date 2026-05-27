package main

import (
	"sync"
	"time"
)

type cacheEntry struct {
	result    InspectResult
	expiresAt time.Time
}

// Cache is a simple in-memory TTL cache for inspection results.
type Cache struct {
	mu      sync.RWMutex
	entries map[string]cacheEntry
	ttl     time.Duration
}

func NewCache(ttl time.Duration) *Cache {
	c := &Cache{
		entries: make(map[string]cacheEntry),
		ttl:     ttl,
	}
	go c.cleanup()
	return c
}

func (c *Cache) Get(key string) (InspectResult, bool) {
	c.mu.RLock()
	defer c.mu.RUnlock()
	e, ok := c.entries[key]
	if !ok || time.Now().After(e.expiresAt) {
		return InspectResult{}, false
	}
	return e.result, true
}

func (c *Cache) Set(key string, result InspectResult) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.entries[key] = cacheEntry{
		result:    result,
		expiresAt: time.Now().Add(c.ttl),
	}
}

func (c *Cache) cleanup() {
	ticker := time.NewTicker(60 * time.Second)
	defer ticker.Stop()
	for range ticker.C {
		c.mu.Lock()
		now := time.Now()
		for k, e := range c.entries {
			if now.After(e.expiresAt) {
				delete(c.entries, k)
			}
		}
		c.mu.Unlock()
	}
}
