<?php

namespace Tests\Feature\Admin;

use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ReportCardService;
use Illuminate\Support\Facades\Artisan;

class ReportCardTest extends AdminTestCase
{
    private SchoolClass $class;
    private Student $student;
    private ReportCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        // ReportCardService renders Blade views to generate PDFs. Those views define
        // a PHP helper function (ordinal()) inline, which causes "Cannot redeclare"
        // if the same compiled-view file is included more than once in a process.
        // Mock the service so no Blade rendering occurs in these HTTP tests; the
        // underlying service logic is exercised by dedicated unit/integration tests.
        $this->mock(ReportCardService::class, function ($mock) {
            $mock->shouldReceive('computeClassStatistics')->andReturn(null);
            // generatePdf() returns string (pdf_path); return empty string to satisfy type
            $mock->shouldReceive('generatePdf')->andReturn('');
            $mock->shouldReceive('generateAllForClass')->andReturn(1);
        });

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 2',
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $this->card = ReportCard::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/report-cards');

        $response->assertOk();
        $response->assertViewIs('admin.report-cards.index');
    }

    public function test_index_passes_classes_and_terms_to_view(): void
    {
        $response = $this->asAdmin()->get('/report-cards');

        $response->assertViewHas('classes');
        $response->assertViewHas('terms');
        $response->assertViewHas('current');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/report-cards')->assertRedirect();
    }

    // ── classCards ────────────────────────────────────────────────────────────

    public function test_class_cards_returns_200(): void
    {
        $response = $this->asAdmin()->get(
            "/report-cards/class?class_id={$this->class->id}&term_id={$this->term->id}"
        );

        $response->assertOk();
        $response->assertViewIs('admin.report-cards.class');
    }

    public function test_class_cards_passes_cards_to_view(): void
    {
        $response = $this->asAdmin()->get(
            "/report-cards/class?class_id={$this->class->id}&term_id={$this->term->id}"
        );

        $cards = $response->viewData('cards');
        $this->assertCount(1, $cards);
        $this->assertSame($this->card->id, $cards->first()->id);
    }

    public function test_class_cards_requires_class_id(): void
    {
        $response = $this->asAdmin()->get("/report-cards/class?term_id={$this->term->id}");

        $response->assertSessionHasErrors('class_id');
    }

    public function test_class_cards_requires_term_id(): void
    {
        $response = $this->asAdmin()->get("/report-cards/class?class_id={$this->class->id}");

        $response->assertSessionHasErrors('term_id');
    }

    // ── computeStats ─────────────────────────────────────────────────────────

    public function test_compute_stats_redirects_with_success(): void
    {
        $response = $this->asAdmin()->post('/report-cards/compute', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    // ── generate single ───────────────────────────────────────────────────────

    public function test_generate_single_redirects(): void
    {
        $response = $this->asAdmin()->post("/report-cards/{$this->card->id}/generate");

        $response->assertRedirect();
    }

    // ── generateAll ───────────────────────────────────────────────────────────

    public function test_generate_all_redirects_with_success(): void
    {
        $response = $this->asAdmin()->post('/report-cards/generate-all', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    // ── download ─────────────────────────────────────────────────────────────

    public function test_download_redirects_back_when_pdf_not_generated(): void
    {
        // card has no pdf_path set
        $response = $this->asAdmin()->get("/report-cards/{$this->card->id}/download");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── editRemarks ───────────────────────────────────────────────────────────

    public function test_edit_remarks_returns_200(): void
    {
        $response = $this->asAdmin()->get("/report-cards/{$this->card->id}/remarks");

        $response->assertOk();
        $response->assertViewIs('admin.report-cards.remarks');
    }

    public function test_edit_remarks_passes_report_card_to_view(): void
    {
        $response = $this->asAdmin()->get("/report-cards/{$this->card->id}/remarks");

        $reportCard = $response->viewData('reportCard');
        $this->assertSame($this->card->id, $reportCard->id);
    }

    // ── updateRemarks ─────────────────────────────────────────────────────────

    public function test_update_remarks_saves_teacher_and_head_remarks(): void
    {
        $response = $this->asAdmin()->put("/report-cards/{$this->card->id}/remarks", [
            'class_teacher_remark' => 'Excellent student, keep it up.',
            'headmaster_remark'    => 'Very impressive performance.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('report_cards', [
            'id'                   => $this->card->id,
            'class_teacher_remark' => 'Excellent student, keep it up.',
            'headmaster_remark'    => 'Very impressive performance.',
        ]);
    }

    public function test_update_remarks_allows_null_values(): void
    {
        $response = $this->asAdmin()->put("/report-cards/{$this->card->id}/remarks", [
            'class_teacher_remark' => null,
            'headmaster_remark'    => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_update_remarks_rejects_remark_over_1000_chars(): void
    {
        $response = $this->asAdmin()->put("/report-cards/{$this->card->id}/remarks", [
            'class_teacher_remark' => str_repeat('a', 1001),
        ]);

        $response->assertSessionHasErrors('class_teacher_remark');
    }
}
