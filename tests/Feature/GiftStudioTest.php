<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Version;
use App\Services\PdfEngine;
use App\Support\GiftGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The gift edition (STUDIO_EDITION=gift): the 10 Gift page — a main picture, up to three thumbnails packed from the
 * right, a card in the page's colour, the client's logo in a tab and the category in a tag — and the trio page.
 */
class GiftStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['studio.password' => 'first-pass-1', 'studio.edition' => 'gift']);
        $this->app->instance(PdfEngine::class, new class extends PdfEngine
        {
            public function __construct() {}

            public function run(string $command, array $job, string $workDir): array
            {
                File::ensureDirectoryExists($workDir);
                if ($command === 'page') {
                    File::put($job['pdf'], '%PDF page');
                    File::put($job['thumb'], 'jpg');

                    return ['ok' => true, 'pdf' => basename($job['pdf']), 'thumb' => basename($job['thumb'])];
                }
                if ($command === 'compose') {
                    File::put($job['out'], '%PDF book');

                    return ['ok' => true, 'pages' => 37];
                }

                return ['ok' => true, 'pages' => 36, 'width' => 566.93, 'height' => 311.81];
            }
        });
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
    }

    private function gift(int $pictures, string $kind = 'project'): Project
    {
        $this->post(route('studio.projects.store'), ['kind' => $kind])->assertRedirect();
        $project = Project::latest('id')->first();
        if ($pictures) {
            $files = array_map(fn ($i) => UploadedFile::fake()->image("g$i.jpg", 1600, 900 + $i), range(1, $pictures));
            $this->post(route('studio.projects.images.store', $project), ['images' => $files])->assertSessionHasNoErrors();
        }

        return $project->fresh();
    }

    public function test_a_new_gift_opens_with_the_gift_figures_category_and_colour(): void
    {
        $project = $this->gift(0);
        $this->assertSame(GiftGeometry::DEFAULT_STATS, $project->stats);
        $this->assertSame('هديـــة', $project->title);
        $this->get(route('studio.projects.edit', $project))->assertInertia(fn (Assert $p) => $p->component('Project')
            ->where('app.edition', 'gift')->where('app.page.w', GiftGeometry::W)->where('project.color', GiftGeometry::DEFAULT_COLOR)
            ->where('categories', GiftGeometry::CATEGORIES)->where('t.studio.edit.title', 'التصنيف'));
    }

    public function test_the_main_picture_fills_the_page_and_thumbnails_pack_from_the_right(): void
    {
        $project = $this->gift(5);
        [$a, $b, $c, $d, $e] = $project->images()->get()->all();
        $places = fn () => collect($project->fresh()->load('images')->windows())->map(fn ($w) => [$w['id'], $w['slot'], $w['x']])->all();

        // nothing marked: the first is the main picture, the next three the thumbnails from the right, the fifth has no place
        $this->assertSame([[$a->id, 'hero', 0.0], [$b->id, 'thumb', 250.83], [$c->id, 'thumb', 130.83], [$d->id, 'thumb', 10.79]], $places());

        // marked: «the main» and «thumbnail 1»; the unmarked fill what is left, in order
        $this->put(route('studio.images.priority', $e), ['priority' => 1])->assertSessionHasNoErrors();
        $this->put(route('studio.images.priority', $d), ['priority' => 2])->assertSessionHasNoErrors();
        $this->assertSame([[$e->id, 'hero', 0.0], [$d->id, 'thumb', 250.83], [$a->id, 'thumb', 130.83], [$b->id, 'thumb', 10.79]], $places());
        $this->put(route('studio.images.priority', $a), ['priority' => 5])->assertSessionHasErrors('priority');

        // two pictures: the one thumbnail sits nearest the card
        $two = $this->gift(2);
        $this->assertSame(['hero', 'thumb'], array_column($two->load('images')->windows(), 'slot'));
        $this->assertSame(250.83, $two->windows()[1]['x']);
    }

    public function test_the_page_is_drawn_in_the_gift_frame_with_its_colour_texts_and_figures(): void
    {
        $project = $this->gift(3);
        $html = $this->post(route('studio.projects.preview', $project), [
            'client' => 'أمانة الجوف', 'title' => 'هديــة', 'description' => 'بوكس خشبي ملبس بالسدو', 'color' => '#142515',
            'stats' => [['value' => '50', 'label' => 'عدد النسخ'], ['value' => '4', 'label' => 'مواد مستخدمه'], ['value' => '1', 'label' => 'حجم']],
        ])->assertOk()->getContent();
        $this->assertStringContainsString('size: 566.93pt 311.81pt', $html);
        $this->assertStringContainsString('id="card" d="'.GiftGeometry::CARD.'" fill="#142515" fill-opacity="0.85"', $html);
        $this->assertStringContainsString('أمانة الجوف', $html);
        $this->assertStringContainsString('id="tagText"', $html);
        $this->assertSame(3, substr_count($html, 'class="stat"'));
        $this->assertSame(2, substr_count($html, 'class="cell thumb"'));
        $this->assertSame(1, substr_count($html, 'class="cell hero"'));
        $this->assertStringNotContainsString('class="more"', $html);          // no «more details» button on a gift page

        $this->post(route('studio.projects.preview', $project), ['color' => 'brown'])->assertSessionHasErrors('color');
        $this->put(route('studio.projects.update', $project), ['client' => 'x', 'color' => '#2f503d', 'stats' => []])->assertSessionHasNoErrors();
        $this->assertSame('#2F503D', $project->fresh()->color);
        $this->assertStringNotContainsString('class="stat"', $this->post(route('studio.projects.preview', $project), ['stats' => []])->getContent());
    }

    public function test_the_logo_sits_in_its_tab_and_the_tab_grows_with_it(): void
    {
        $project = $this->gift(1);
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"><rect width="300" height="100"/></svg>');
        $this->post(route('studio.projects.logo.store', $project), ['logo' => $svg])->assertSessionHasNoErrors();
        $logo = $project->fresh()->logo;
        $this->assertEquals([300, 100], [$logo['w'], $logo['h']]);
        Storage::disk('local')->assertExists($logo['path']);

        // fitted into 66 × 20 pt: as wide as it may be (60 × 20), and the tab ends as far past it as it starts
        $place = GiftGeometry::logoPlace($logo);
        $this->assertSame(['x' => 7.0, 'y' => 19.14, 'w' => 60.0, 'h' => 20.0], $place['box']);
        $this->assertSame(74.0, $place['tab']);
        $html = $this->post(route('studio.projects.preview', $project), [])->getContent();
        $this->assertStringContainsString('class="logo"', $html);
        $this->get(route('studio.projects.logo', $project))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->post(route('studio.projects.logo.store', $project), ['logo' => UploadedFile::fake()->create('logo.exe', 10)])->assertSessionHas('error');
        $this->delete(route('studio.projects.logo.destroy', $project));
        $this->assertNull($project->fresh()->logo);
        Storage::disk('local')->assertMissing($logo['path']);
        $this->assertStringNotContainsString('class="logo"', $this->post(route('studio.projects.preview', $project), [])->getContent());
    }

    public function test_a_trio_page_is_three_pictures_side_by_side_and_the_tag(): void
    {
        $project = $this->gift(3, 'trio');
        $this->assertSame('trio', $project->kind);
        $this->assertSame('دروع', $project->title);
        $wins = $project->load('images')->windows();
        $this->assertSame(['panel', 'panel', 'panel'], array_column($wins, 'slot'));
        $this->assertSame([378.0, 189.0, 0.0], array_column($wins, 'x'));
        $html = $this->post(route('studio.projects.preview', $project), ['title' => 'دروع', 'client' => 'لا يظهر'])->getContent();
        $this->assertStringContainsString('id="tagText"', $html);
        foreach (['id="card"', 'لا يظهر', 'id="tab"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }
    }

    public function test_approving_a_gift_page_puts_it_in_the_gift_profile(): void
    {
        $this->post(route('studio.profile.store'), ['file' => UploadedFile::fake()->create('10Gift Profile.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();
        $project = $this->gift(2);
        $this->post(route('studio.projects.approve', $project), ['at' => 34])->assertSessionHasNoErrors();
        $project->refresh();
        $this->assertSame('approved', $project->status);
        $this->assertSame(34, $project->profile_page);
        $this->assertSame(Version::current()->id, $project->profile_version_id);
    }
}
