<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();           // dipakai di path S3
            $table->string('name');
            $table->string('primary_color', 7)->default('#C42A27');
            $table->string('logo_path')->nullable();       // relatif terhadap disk "public"
            $table->string('mail_from_name')->nullable();
            $table->json('settings')->nullable();          // mis. {"documents": {...}}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('company_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('domain', 190)->unique();       // huruf kecil, tanpa port
            $table->timestamps();
        });

        // Token reset password per pelamar (email yang sama boleh ada di beberapa perusahaan)
        Schema::create('applicant_password_resets', function (Blueprint $table) {
            $table->unsignedBigInteger('applicant_data_id')->primary();
            $table->char('token_hash', 64);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_password_resets');
        Schema::dropIfExists('company_domains');
        Schema::dropIfExists('companies');
    }
};
