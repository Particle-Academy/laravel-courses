<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // online courses are consumed via the Classroom UX (the React
            // companion kit); in_person courses are taught live and tracked
            // via admin-issued completions; hybrid courses combine both.
            $table->string('delivery_mode')->default('online')->after('is_required');
            $table->index('delivery_mode');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['delivery_mode']);
            $table->dropColumn('delivery_mode');
        });
    }
};
