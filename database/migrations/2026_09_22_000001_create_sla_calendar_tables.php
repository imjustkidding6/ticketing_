<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business-hours calendar: a timezone, a set of weekly open windows, and a
 * holiday list. Purely additive — nothing existing is touched, and nothing reads
 * these tables yet. The arithmetic that will consume them (App\Support\SlaClock)
 * ships alongside, proven in isolation, with no consumer wired up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Seeded from TenantTime::timezone() at creation and then owned here.
            // JSM's rule: the calendar carries its own zone; it is not the display zone,
            // and a team spanning two regions needs two calendars, not one.
            $table->string('timezone', 64);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('sla_calendar_hours', function (Blueprint $table) {
            $table->id();
            // tenant_id on the children too, not just the parent: a child reachable only
            // through its parent is still a child someone will query directly. Precedent:
            // ticket_histories carries one for exactly this reason.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_calendar_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');   // ISO-8601, 1 = Mon .. 7 = Sun
            // Minutes from local midnight, not TIME columns: the arithmetic is integer
            // addition against a local midnight, and TIME would force a parse in the hot
            // loop and invite a string comparison across a DST change.
            $table->unsignedSmallInteger('start_minute'); // 0..1439
            $table->unsignedSmallInteger('end_minute');   // 1..1440; 1440 == closes at midnight
            $table->timestamps();
            // Several rows per (calendar, weekday) is the mechanism for split shifts —
            // 09:00-12:00 and 13:00-17:00 is two rows. A closed day has no rows at all.
            $table->unique(['sla_calendar_id', 'day_of_week', 'start_minute'], 'sla_cal_hours_unique');
            $table->index(['tenant_id']);
        });

        Schema::create('sla_calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('month'); // 1..12
            $table->unsignedTinyInteger('day');   // 1..31
            // NULL means the holiday recurs every year, and NULL is the default on
            // purpose: the loudest complaint about JSM's calendars is the ones nobody
            // re-populates in January. Rotting must require opting in.
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();
            $table->unique(['sla_calendar_id', 'month', 'day', 'year'], 'sla_cal_holidays_unique');
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_calendar_holidays');
        Schema::dropIfExists('sla_calendar_hours');
        Schema::dropIfExists('sla_calendars');
    }
};
