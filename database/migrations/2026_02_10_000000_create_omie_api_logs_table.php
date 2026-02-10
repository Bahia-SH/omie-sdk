<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::create('omie_api_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('app_key')->index()->index();
            $table->string('service_path')->index();
            $table->string('method')->index();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('omie_status_code')->nullable();
            $table->string('omie_status_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_origem')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('error_trace')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('omie_api_logs');
    }
};

