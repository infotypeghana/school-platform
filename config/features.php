<?php

/**
 * Feature Gating Configuration
 *
 * Defines which features are available per subscription package tier.
 * Features are checked via the FeatureGate service — never hardcode
 * plan names in business logic; always reference a feature key.
 *
 * Tier hierarchy: basic < standard < premium < enterprise
 * Each tier implicitly includes all tiers below it.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Plan Tier Hierarchy
    |--------------------------------------------------------------------------
    | Lower number = lower tier. FeatureGate uses this to resolve
    | "minimum tier required" checks without listing every tier.
    */
    'tiers' => [
        'trial'      => 0,
        'basic'      => 1,
        'standard'   => 2,
        'premium'    => 3,
        'enterprise' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Definitions
    |--------------------------------------------------------------------------
    | key => [
    |     'label'       — Human-readable name (shown in upgrade prompts)
    |     'min_tier'    — Minimum package tier required
    |     'description' — Shown in pricing/upgrade UI
    | ]
    */
    'definitions' => [

        // ── Core (all plans including trial) ─────────────────────────────────
        'students'              => ['label' => 'Student Management',     'min_tier' => 'basic',      'description' => 'Manage student records, profiles and documents.'],
        'teachers'              => ['label' => 'Teacher Management',     'min_tier' => 'basic',      'description' => 'Manage teacher records and assignments.'],
        'classes'               => ['label' => 'Class Management',       'min_tier' => 'basic',      'description' => 'Create and manage school classes.'],
        'attendance'            => ['label' => 'Attendance Tracking',    'min_tier' => 'basic',      'description' => 'Daily student attendance with reports.'],
        'fees'                  => ['label' => 'Fee Management',         'min_tier' => 'basic',      'description' => 'Collect and track student fees.'],
        'admissions'            => ['label' => 'Admissions Portal',      'min_tier' => 'basic',      'description' => 'Online admission application management.'],
        'announcements'         => ['label' => 'Announcements',          'min_tier' => 'basic',      'description' => 'School-wide announcement broadcasting.'],
        'timetable'             => ['label' => 'Timetable',              'min_tier' => 'basic',      'description' => 'Weekly class timetable management.'],
        'parent_portal'         => ['label' => 'Parent Portal',          'min_tier' => 'basic',      'description' => 'Parents view results, fees, and attendance.'],
        'website'               => ['label' => 'School Website',         'min_tier' => 'basic',      'description' => 'Public-facing school website with all pages.'],
        'report_cards'          => ['label' => 'Report Cards (Basic)',   'min_tier' => 'basic',      'description' => 'Generate and print standard report cards.'],

        // ── Standard tier ─────────────────────────────────────────────────────
        'sms_notifications'     => ['label' => 'SMS Notifications',      'min_tier' => 'standard',   'description' => 'Send SMS alerts to parents and teachers.'],
        'assessments'           => ['label' => 'Online Assessments',     'min_tier' => 'standard',   'description' => 'Create and grade class assessments online.'],
        'lesson_notes'          => ['label' => 'Lesson Notes & Plans',   'min_tier' => 'standard',   'description' => 'Teacher lesson note and curriculum planning.'],
        'feeding_fees'          => ['label' => 'Feeding Fee Management', 'min_tier' => 'standard',   'description' => 'Track and collect student feeding fees.'],
        'promotions'            => ['label' => 'Class Promotions',       'min_tier' => 'standard',   'description' => 'Promote students to next class in bulk.'],
        'data_export'           => ['label' => 'Data Export & Backup',   'min_tier' => 'standard',   'description' => 'Export school data as ZIP/CSV files.'],
        'analytics'             => ['label' => 'Basic Analytics',        'min_tier' => 'standard',   'description' => 'Fee collection and attendance analytics.'],
        'curriculum'            => ['label' => 'Curriculum Management',  'min_tier' => 'standard',   'description' => 'GES curriculum strands and schemes of work.'],

        // ── Premium tier ──────────────────────────────────────────────────────
        'report_cards_pdf'      => ['label' => 'PDF Report Cards',       'min_tier' => 'premium',    'description' => 'Auto-generated professional PDF report cards.'],
        'biometric'             => ['label' => 'Biometric Attendance',   'min_tier' => 'premium',    'description' => 'ZKTeco device integration for biometric attendance.'],
        'api_access'            => ['label' => 'REST API Access',        'min_tier' => 'premium',    'description' => 'Mobile API access for third-party integrations.'],
        'custom_branding'       => ['label' => 'Custom Branding',        'min_tier' => 'premium',    'description' => 'Custom logo, colors, and email templates.'],
        'teacher_portal'        => ['label' => 'Teacher Portal',         'min_tier' => 'premium',    'description' => 'Teacher self-service portal for score entry.'],
        'advanced_analytics'    => ['label' => 'Advanced Analytics',     'min_tier' => 'premium',    'description' => 'Detailed academic performance and financial reports.'],
        'bulk_sms'              => ['label' => 'Bulk SMS Campaigns',     'min_tier' => 'premium',    'description' => 'Mass SMS to parents with custom messages.'],

        // ── Enterprise tier ────────────────────────────────────────────────────
        'payroll'               => ['label' => 'Staff Payroll',          'min_tier' => 'enterprise', 'description' => 'Teacher and staff salary management.'],
        'multi_branch'          => ['label' => 'Multi-Branch Support',   'min_tier' => 'enterprise', 'description' => 'Manage multiple school branches under one account.'],
        'custom_domain'         => ['label' => 'Custom Domain',          'min_tier' => 'enterprise', 'description' => 'Use your own domain instead of schoolms.com.gh.'],
        'dedicated_support'     => ['label' => 'Dedicated Support',      'min_tier' => 'enterprise', 'description' => '24/7 priority support with dedicated account manager.'],
        'audit_log_export'      => ['label' => 'Audit Log Export',       'min_tier' => 'enterprise', 'description' => 'Full audit trail export for compliance.'],
        'sso'                   => ['label' => 'Single Sign-On (SSO)',   'min_tier' => 'enterprise', 'description' => 'SAML/OAuth SSO integration.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Limits (MB) per Tier
    |--------------------------------------------------------------------------
    */
    'storage_limits' => [
        'trial'      => 100,
        'basic'      => 500,
        'standard'   => 2_000,
        'premium'    => 10_000,
        'enterprise' => 50_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Limits per Tier (0 = unlimited)
    |--------------------------------------------------------------------------
    */
    'student_limits' => [
        'trial'      => 50,
        'basic'      => 500,
        'standard'   => 1_000,
        'premium'    => 5_000,
        'enterprise' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Credit Limits per Term per Tier (0 = unlimited)
    |--------------------------------------------------------------------------
    */
    'sms_limits' => [
        'trial'      => 50,
        'basic'      => 0,     // basic has no SMS
        'standard'   => 500,
        'premium'    => 2_000,
        'enterprise' => 0,
    ],

];
