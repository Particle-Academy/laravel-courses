<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates are written by fine-print, which draws a JSON document, not
 * HTML. A template's design now lives here: a fancy-template envelope
 * (`{"fancyTemplate": 1, "writer": "fine-print", "variables": ..., "document": ...}`).
 * The html/css/blade_view columns stay for one minor so nothing is lost.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->json('document')->nullable()->after('css');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->dropColumn('document');
        });
    }
};
