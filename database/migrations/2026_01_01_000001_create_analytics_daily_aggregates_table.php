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
        Schema::create('analytics_daily_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_name', 128);
            // Calendar date in the application timezone.
            $table->date('date');
            $table->unsignedBigInteger('event_count')->default(0);
            $table->unsignedBigInteger('unique_users')->nullable();
            $table->timestamps();

            $table->unique(['event_name', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_aggregates');
    }
};
