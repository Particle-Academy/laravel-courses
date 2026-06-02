<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('curriculums', function (Blueprint $table): void {
            $table->decimal('price', 8, 2)->nullable()->after('description');
            $table->string('currency', 3)->nullable()->after('price');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->decimal('price', 8, 2)->nullable()->after('description');
            $table->string('currency', 3)->nullable()->after('price');
            $table->json('highlights')->nullable()->after('currency');
            $table->boolean('is_required')->default(false)->after('highlights');
            $table->decimal('hours', 5, 2)->nullable()->after('estimated_minutes');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('completed_at');
            $table->index('expires_at');
        });

        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->text('grader_notes')->nullable()->after('passed');
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('certificate_number')->nullable()->unique()->after('verification_code');
            $table->unsignedBigInteger('issued_by_user_id')->nullable()->after('certificate_template_id');
            $table->timestamp('revoked_at')->nullable()->after('pdf_path');
            $table->text('revocation_reason')->nullable()->after('revoked_at');
            $table->text('notes')->nullable()->after('revocation_reason');
            $table->index('issued_by_user_id');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropIndex(['revoked_at']);
            $table->dropIndex(['issued_by_user_id']);
            $table->dropColumn(['certificate_number', 'issued_by_user_id', 'revoked_at', 'revocation_reason', 'notes']);
        });

        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->dropColumn('grader_notes');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['price', 'currency', 'highlights', 'is_required', 'hours']);
        });

        Schema::table('curriculums', function (Blueprint $table): void {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
