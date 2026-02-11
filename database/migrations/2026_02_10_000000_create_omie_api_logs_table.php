<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::create('omie_api_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('app_key');
            $table->string('service_path');
            $table->string('method');
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('omie_status_code')->nullable();
            $table->string('omie_status_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_origem')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('error_trace')->nullable();
            $table->string('event_class')->nullable();
            $table->json('event_params')->nullable();
            $table->timestamps();

            $table->index(['app_key', 'service_path', 'method']);
            $table->index(['event_class']);
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('omie_api_logs');
    }
};
