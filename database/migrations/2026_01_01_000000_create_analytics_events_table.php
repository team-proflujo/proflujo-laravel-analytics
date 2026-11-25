<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        /** @var string|null $connection */
        $connection = config('analytics.database.connection');

        return $connection ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_name', 128);
            $table->string('category', 128)->nullable();
            $table->string('user_id', 191)->nullable();
            $table->json('properties')->nullable();
            // Always stored in UTC.
            $table->dateTime('occurred_at', 6);
            $table->dateTime('created_at', 6)->nullable();

            $table->index('event_name');
            $table->index('occurred_at');
            $table->index('category');
            $table->index(['event_name', 'occurred_at']);
            $table->index(['category', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
