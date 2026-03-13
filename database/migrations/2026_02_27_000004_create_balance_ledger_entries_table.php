<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount_decimal', 14, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('type', 16); // credit | debit
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->unique(['payment_id', 'type'], 'balance_ledger_payment_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_ledger_entries');
    }
};
