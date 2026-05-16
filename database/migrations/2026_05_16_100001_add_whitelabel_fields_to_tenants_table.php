<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // White-label branding
            $table->string('favicon')->nullable()->after('logo');            // favicon file path
            $table->string('secondary_color', 20)->nullable()->after('primary_color'); // hex color
            $table->string('font_family', 100)->nullable()->after('secondary_color');  // e.g. 'Inter'

            // SMS white-labeling
            $table->string('sms_sender_id', 11)->nullable()->after('font_family');  // max 11 chars, letters only

            // Email white-labeling
            $table->string('email_from_name', 100)->nullable()->after('sms_sender_id');
            $table->string('email_from_address', 150)->nullable()->after('email_from_name');
            $table->string('email_header_color', 20)->nullable()->after('email_from_address');

            // Custom domain
            $table->string('custom_domain', 253)->nullable()->after('domain'); // e.g. sms.myschool.edu.gh

            // Report card customization
            $table->string('report_card_template', 50)->default('standard')->after('custom_domain'); // standard|compact|detailed
            $table->text('report_card_footer')->nullable()->after('report_card_template');

            // Login page
            $table->text('login_welcome_text')->nullable()->after('report_card_footer');
            $table->string('login_bg_color', 20)->nullable()->after('login_welcome_text');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'favicon', 'secondary_color', 'font_family', 'sms_sender_id',
                'email_from_name', 'email_from_address', 'email_header_color',
                'custom_domain', 'report_card_template', 'report_card_footer',
                'login_welcome_text', 'login_bg_color',
            ]);
        });
    }
};
