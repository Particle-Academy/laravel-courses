<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One row per session a learner attended in person. Hours are what
        // count toward completion; the date is what an auditor asks for.
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('attended_on');
            $table->decimal('hours', 5, 2);
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'attended_on']);
            $table->index('recorded_by_user_id');
        });

        Schema::table('courses', function (Blueprint $table): void {
            // In-person hours a learner must attend. Null on an in_person
            // course means "all of `hours`"; on a hybrid course it means the
            // in-person part is not tracked, because there `hours` is the
            // whole course and cannot be read as seat time.
            $table->decimal('attendance_hours', 5, 2)->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('attendance_hours');
        });

        Schema::dropIfExists('attendances');
    }
};
