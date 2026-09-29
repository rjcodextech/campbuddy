<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deals grow up:
 *
 *  - A deal with no event_id is a **default deal**: made once under
 *    Admin → Default deals and shown at every event in its countries
 *    (offers.countries, e.g. ["IN"]; empty = every country), now and in
 *    future. An event can hide one (offer_event_hidden), e.g. when it
 *    clashes with the event's own sponsor.
 *  - A deal card says more: the company (brand), its website, a short
 *    highlight ("20% OFF"), terms, an optional coupon code and the button
 *    text. opens_in_app = false opens the link in a new tab, so a referral
 *    or affiliate link keeps its credit (a framed site loses its cookies).
 *  - The contact form is set per deal (offers.lead_form): each of name,
 *    company, email and phone is off / optional / required with its own
 *    label, plus an optional list of products to tick (Knit Pay Pro / UPI).
 *    Empty = the old form (name + email required, phone optional).
 *  - A lead may therefore have no name, and may carry a company and the
 *    products ticked.
 *
 * Every existing deal and lead keeps working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->change();
            $table->string('description', 500)->change();

            $table->string('brand', 120)->nullable()->after('event_id');
            $table->string('website', 120)->nullable()->after('brand');
            $table->string('highlight', 40)->nullable()->after('title');
            $table->string('terms', 255)->nullable()->after('description');
            $table->string('coupon_code', 60)->nullable()->after('terms');
            $table->string('cta_label', 40)->nullable()->after('url');
            $table->boolean('opens_in_app')->default(true)->after('cta_label');
            $table->json('countries')->nullable()->after('opens_in_app');
            $table->json('lead_form')->nullable()->after('capture_leads');
        });

        Schema::create('offer_event_hidden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['offer_id', 'event_id']);
        });

        Schema::table('offer_leads', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();
            $table->string('company', 191)->nullable()->after('name');
            $table->json('choices')->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('offer_leads', function (Blueprint $table) {
            $table->dropColumn(['company', 'choices']);
        });

        Schema::dropIfExists('offer_event_hidden');

        // Default deals have no event to go back to.
        \Illuminate\Support\Facades\DB::table('offers')->whereNull('event_id')->delete();

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['brand', 'website', 'highlight', 'terms', 'coupon_code', 'cta_label', 'opens_in_app', 'countries', 'lead_form']);
        });
    }
};
