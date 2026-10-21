<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Project;
use App\Models\Version;
use App\Services\Fonts;
use App\Services\PdfEngine;
use App\Support\Collage;
use App\Support\PageGeometry;
use App\Support\StudioAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The studio: one team password, project pages built from a form and tiled without gaps, and the company
 * profile edited page by page — approving puts the page where asked and every other project page keeps its place.
 */
class StudioTest extends TestCase
{
    use RefreshDatabase;

    /** Page counts of the fake PDFs, keyed by their path: the engine stand-in only does the arithmetic. */
    private array $books = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['studio.password' => 'first-pass-1']);

        $books = &$this->books;
        $this->app->instance(PdfEngine::class, new class($books) extends PdfEngine
        {
            public array $ops = [];

            public function __construct(private array &$books) {}

            public function run(string $command, array $job, string $workDir): array
            {
                File::ensureDirectoryExists($workDir);
                if ($command === 'page') {
                    File::put($job['pdf'], '%PDF page');
                    File::put($job['thumb'], 'jpg');

                    return ['ok' => true, 'pdf' => basename($job['pdf']), 'thumb' => basename($job['thumb'])];
                }
                if ($command === 'compose') {
                    $pages = $this->books[$job['base']] ?? 45;
                    foreach ($job['ops'] as $op) {
                        $this->ops[] = $op;
                        $pages += ['insert' => 1, 'replace' => 0, 'remove' => -1, 'move' => 0][$op['op']];
                    }
                    File::put($job['out'], '%PDF book');
                    $this->books[$job['out']] = $pages;

                    return ['ok' => true, 'pages' => $pages];
                }
                if ($command === 'inspect') {
                    // a file moved into place keeps the count it was made with; an upload is a 45-page profile
                    $pages = $this->books[$job['pdf']] ?? null;
                    if ($pages === null) {
                        foreach ($this->books as $path => $n) {
                            if (! is_file($path)) {
                                $pages = $n;                    // the temporary file that was just moved here
                                unset($this->books[$path]);
                            }
                        }
                    }
                    $this->books[$job['pdf']] = $pages ??= 45;

                    return ['ok' => true, 'pages' => $pages, 'width' => 960, 'height' => 540];
                }

                return ['ok' => false, 'error' => ['code' => 'unknown_command']];
            }
        });
    }

    private function profile(): Version
    {
        $this->post(route('studio.profile.store'), ['file' => UploadedFile::fake()->create('الملف التعريفي.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();

        return Version::current();
    }

    private function projectWithPictures(int $count = 3): Project
    {
        $this->post(route('studio.projects.store'))->assertRedirect();
        $project = Project::latest('id')->first();
        $files = array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 1200 + 100 * $i, 800), range(1, $count));
        $this->post(route('studio.projects.images.store', $project), ['images' => $files])->assertSessionHasNoErrors();

        return $project->fresh();
    }

    public function test_the_studio_asks_for_the_team_password(): void
    {
        $this->get(route('studio.index'))->assertRedirect(route('studio.login'));
        $this->post(route('studio.login.attempt'), ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->get(route('studio.index'))->assertRedirect(route('studio.login'));

        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1'])->assertRedirect(route('studio.index'));
        $this->get(route('studio.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Index')->where('profile', null));
    }

    public function test_a_new_password_signs_the_team_out(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->get(route('studio.index'))->assertOk();

        // somebody else changes the password: this session's key no longer opens the door
        StudioAccess::set('second-pass-2');
        $this->get(route('studio.index'))->assertRedirect(route('studio.login'));
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1'])->assertSessionHasErrors('password');
        $this->post(route('studio.login.attempt'), ['password' => 'second-pass-2'])->assertRedirect(route('studio.index'));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->put(route('studio.settings.password'), ['current' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current');
        $this->put(route('studio.settings.password'), ['current' => 'first-pass-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasNoErrors();
        $this->get(route('studio.index'))->assertOk();          // the one who changed it stays in
        $this->assertTrue(StudioAccess::check('brand-new-pass'));
        $this->assertFalse(StudioAccess::check('first-pass-1'));
    }

    public function test_a_new_project_opens_with_the_profiles_three_default_statistics(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->post(route('studio.projects.store'));
        $project = Project::first();

        $this->assertSame(PageGeometry::DEFAULT_STATS, $project->stats);
        $this->get(route('studio.projects.edit', $project))->assertInertia(fn (Assert $p) => $p->component('Project')
            ->where('project.stats.0.value', '100+')->where('project.stats.1.value', '4 مناطق')->where('project.stats.2.value', '1 يـــوم'));
    }

    public function test_pictures_are_lightened_and_one_priority_belongs_to_one_picture(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->post(route('studio.projects.store'));
        $project = Project::first();
        $this->post(route('studio.projects.images.store', $project), ['images' => [UploadedFile::fake()->image('big.jpg', 5000, 3000)]]);
        $big = $project->images()->first();
        $this->assertSame([2400, 1440], [$big->width, $big->height]);
        Storage::disk('local')->assertExists([$big->path, $big->preview_path]);

        $project = $this->projectWithPictures(3);
        [$a, $b] = $project->images()->get()->all();
        $this->put(route('studio.images.priority', $a), ['priority' => 1]);
        $this->put(route('studio.images.priority', $b), ['priority' => 1]);
        $this->assertNull($a->fresh()->priority);
        $this->assertSame(1, $b->fresh()->priority);
        $this->put(route('studio.images.priority', $b), ['priority' => 4])->assertSessionHasErrors('priority');

        // every picture has a cell, and the cells are the page — nothing missing, nothing twice
        $layout = $project->fresh()->layout;
        $this->assertEqualsCanonicalizing($project->images()->pluck('id')->all(), array_column($layout, 'id'));
        $this->assertEqualsWithDelta(960 * 540, array_sum(array_map(fn ($c) => $c['w'] * $c['h'], $layout)), 1.0);
    }

    public function test_the_collage_tiles_the_page_without_gaps_and_keeps_the_chosen_pictures_in_view(): void
    {
        $images = [['id' => 1, 'w' => 1500, 'h' => 1000, 'priority' => 1], ['id' => 2, 'w' => 1300, 'h' => 1000, 'priority' => 2]];
        foreach (range(3, 11) as $id) {
            $images[] = ['id' => $id, 'w' => $id % 3 ? 1500 : 800, 'h' => 1000, 'priority' => null];
        }
        $cells = Collage::arrange($images, 99);
        $this->assertSame($cells, Collage::arrange($images, 99), 'the same seed must give the same page');
        $this->assertNotSame($cells, Collage::arrange($images, 100));

        $this->assertEqualsWithDelta(960 * 540, array_sum(array_map(fn ($c) => $c['w'] * $c['h'], $cells)), 1.0);
        foreach ($cells as $i => $a) {
            $this->assertGreaterThanOrEqual(-0.01, $a['x']);
            $this->assertLessThanOrEqual(960.01, $a['x'] + $a['w']);
            $this->assertLessThanOrEqual(540.01, $a['y'] + $a['h']);
            foreach (array_slice($cells, $i + 1) as $b) {
                $overlapW = min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']);
                $overlapH = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']);
                $this->assertFalse($overlapW > 0.5 && $overlapH > 0.5, 'cells overlap');
            }
        }
        $byId = array_column($cells, null, 'id');
        $area = fn ($c) => $c['w'] * $c['h'];
        $this->assertGreaterThan($area($byId[2]), $area($byId[1]) * 1.05, 'priority 1 is the largest');
        foreach (range(3, 11) as $id) {
            $this->assertGreaterThan($area($byId[$id]), $area($byId[2]), 'priority 2 outranks every ordinary picture');
        }
        // every picture shows, and little of what is drawn of it sits behind the text card — the first one least
        foreach ($cells as $c) {
            $v = PageGeometry::visibleBox($c['x'], $c['y'], $c['w'], $c['h']);
            $this->assertNotNull($v, 'a picture is hidden whole behind the card');
            $this->assertLessThan(0.3, $v['hidden']);
        }
        $first = $byId[1];
        $this->assertLessThan(0.2, PageGeometry::visibleBox($first['x'], $first['y'], $first['w'], $first['h'])['hidden']);
    }

    public function test_pictures_are_drawn_only_where_they_show_and_cover_it_without_gaps(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $project = $this->projectWithPictures(7);
        $windows = $project->fresh()->load('images')->windows();
        $this->assertCount(7, $windows);

        foreach ($windows as $w) {
            // the picture covers its window whatever the crop
            $this->assertLessThanOrEqual(0.01, $w['img']['x']);
            $this->assertLessThanOrEqual(0.01, $w['img']['y']);
            $this->assertGreaterThanOrEqual($w['w'] - 0.02, $w['img']['x'] + $w['img']['w']);
            $this->assertGreaterThanOrEqual($w['h'] - 0.02, $w['img']['y'] + $w['img']['h']);
        }
        // every point of the page outside the card is under exactly one window: no gap, no overlap
        for ($x = 1.5; $x < 960; $x += 7) {
            for ($y = 1.5; $y < 540; $y += 7) {
                if (PageGeometry::insideBlob($x, $y)) {
                    continue;
                }
                $under = array_filter($windows, fn ($w) => $x >= $w['x'] && $x < $w['x'] + $w['w'] && $y >= $w['y'] && $y < $w['y'] + $w['h']);
                $this->assertCount(1, $under, "point $x,$y");
            }
        }
    }

    public function test_the_visible_part_of_a_picture_is_chosen_by_hand_and_can_go_back_to_automatic(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->profile();
        $project = $this->projectWithPictures(2);
        $this->post(route('studio.projects.approve', $project), ['at' => 3]);
        $image = $project->images()->first();
        $before = collect($project->fresh()->load('images')->windows())->firstWhere('id', $image->id);

        $this->put(route('studio.images.crop', $image), ['x' => 0.1, 'y' => 0.9, 'zoom' => 2])->assertSessionHasNoErrors();
        $image->refresh();
        $this->assertSame([0.1, 0.9, 2.0], [$image->crop_x, $image->crop_y, $image->crop_zoom]);
        $this->assertTrue($project->fresh()->profile_stale, 'the page in the profile is out of date');
        $after = collect($project->fresh()->load('images')->windows())->firstWhere('id', $image->id);
        $this->assertEqualsWithDelta($before['img']['w'] * 2, $after['img']['w'], 0.05);     // zoomed in twice
        $this->assertSame([$before['x'], $before['y'], $before['w'], $before['h']], [$after['x'], $after['y'], $after['w'], $after['h']]);
        $this->assertStringContainsString('"crop":{"x":0.1,"y":0.9,"zoom":2', json_encode($image->present()));

        $this->put(route('studio.images.crop', $image), ['x' => 2, 'zoom' => 9])->assertSessionHasErrors(['x', 'zoom']);
        $this->put(route('studio.images.crop', $image), ['zoom' => null]);
        $this->assertNull($image->fresh()->crop_zoom);
        $this->assertNull($image->fresh()->present()['crop']);
    }

    public function test_the_preview_is_the_page_and_leaves_out_what_is_empty(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $project = $this->projectWithPictures(2);

        $html = $this->post(route('studio.projects.preview', $project), [
            'client' => 'سكربز', 'title' => 'أكبر معرض أزياء طبية', 'description' => "سطر أول\nسطر ثانٍ",
            'details_url' => 'https://tenevents.sa/x', 'details_label' => '', 'stats' => [['value' => '100+', 'label' => 'معلن']],
        ])->assertOk()->getContent();
        $this->assertStringContainsString('سكربز', $html);
        $this->assertStringContainsString('class="title"', $html);
        $this->assertStringContainsString('href="https://tenevents.sa/x"', $html);
        $this->assertStringContainsString('لتفاصيل أكثر', $html);
        $this->assertSame(1, substr_count($html, 'class="stat"'));
        $this->assertSame(2, substr_count($html, 'class="cell"'));
        $this->assertStringContainsString('dir="ltr">100+', $html);        // a bare number reads «100+», as in the profile

        $html = $this->post(route('studio.projects.preview', $project), ['client' => 'سكربز', 'stats' => []])->getContent();
        $this->assertStringNotContainsString('class="stat"', $html);      // no statistics: nothing in their place
        $this->assertStringNotContainsString('class="more"', $html);      // no link: no button
        $this->assertStringNotContainsString('class="title"', $html);

        $this->post(route('studio.projects.preview', $project), ['details_url' => 'javascript:alert(1)'])->assertSessionHasErrors('details_url');
    }

    public function test_a_picture_page_is_its_picture_alone(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $project = $this->projectWithPictures(1);
        $project->forceFill(['kind' => 'image', 'title' => 'الغلاف'])->save();

        $html = $this->post(route('studio.projects.preview', $project), ['client' => 'لا يظهر', 'title' => 'الغلاف', 'stats' => [['value' => '1', 'label' => 'x']]])->getContent();
        $this->assertSame(1, substr_count($html, 'class="cell"'));
        $this->assertStringContainsString('left: 0pt; top: 0pt; width: 960pt; height: 540pt;', $html);
        foreach (['id="card"', 'class="stat"', 'لا يظهر', 'class="title"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }
        $this->get(route('studio.projects.edit', $project))->assertInertia(fn (Assert $p) => $p->where('project.kind', 'image'));
    }

    public function test_approving_places_the_page_and_every_other_project_keeps_its_place(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);

        $draft = $this->projectWithPictures(1);
        $this->post(route('studio.projects.approve', $draft), ['at' => 12])->assertSessionHas('error.code', 'portfolio_no_profile');

        $v1 = $this->profile();
        $this->assertSame(45, $v1->pages);
        $this->assertSame('الملف التعريفي.pdf', $v1->name);

        $a = $draft;
        $this->post(route('studio.projects.approve', $a), ['at' => 12])->assertSessionHas('success', 'ui.studio.approved');
        $a->refresh();
        $this->assertSame(['approved', 12, 46], [$a->status, $a->profile_page, Version::current()->pages]);
        $this->assertTrue($a->inProfile());

        $b = $this->projectWithPictures(2);
        $this->post(route('studio.projects.approve', $b), ['at' => 5]);
        $this->assertSame([5, 13], [$b->fresh()->profile_page, $a->fresh()->profile_page]);   // A moved down one page

        // an edit after approval: the profile still holds the old page until it is updated in place
        $this->put(route('studio.projects.update', $a), ['client' => 'عميل جديد', 'title' => 'عنوان', 'stats' => []]);
        $this->assertTrue($a->fresh()->profile_stale);
        $this->post(route('studio.projects.refresh', $a))->assertSessionHas('success', 'ui.studio.refreshed');
        $this->assertFalse($a->fresh()->profile_stale);
        $this->assertSame([13, 47, 'update'], [$a->fresh()->profile_page, Version::current()->pages, Version::current()->source]);

        // back to draft: B leaves the file, A moves up
        $this->post(route('studio.projects.unapprove', $b));
        $this->assertSame(['draft', null], [$b->fresh()->status, $b->fresh()->profile_page]);
        $this->assertSame([12, 46], [$a->fresh()->profile_page, Version::current()->pages]);
        $this->assertSame(5, Version::count());              // upload, A, B, A updated, B removed — nothing overwritten

        // a new upload: positions are unknown until somebody links the page
        $this->profile();
        $this->assertFalse($a->fresh()->inProfile());
        $this->post(route('studio.projects.link', $a), ['page' => 99])->assertSessionHas('error.code', 'portfolio_page_out_of_range');
        $this->post(route('studio.projects.link', $a), ['page' => 20]);
        $this->assertTrue($a->fresh()->inProfile());
        $this->assertSame(20, $a->fresh()->profile_page);

        $this->get(route('studio.profile'))->assertInertia(fn (Assert $p) => $p->component('Profile')
            ->has('versions', 6)->where('current.pages', 45)->where('placed.20.id', $a->public_id));
    }

    public function test_an_approved_page_can_be_moved_and_the_pages_between_shift_with_it(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->profile();
        [$a, $b, $c] = [$this->projectWithPictures(1), $this->projectWithPictures(1), $this->projectWithPictures(1)];
        $this->post(route('studio.projects.approve', $a), ['at' => 5]);
        $this->post(route('studio.projects.approve', $b), ['at' => 10]);
        $this->post(route('studio.projects.approve', $c), ['at' => 20]);
        $this->put(route('studio.projects.update', $c), ['client' => 'تعديل', 'title' => '', 'stats' => []]);
        $pages = Version::current()->pages;
        $at = fn () => [$a->fresh()->profile_page, $b->fresh()->profile_page, $c->fresh()->profile_page];

        // later in the book: A leaves 5, everything up to 15 moves up one
        $this->post(route('studio.projects.move', $a), ['to' => 15])->assertSessionHas('success', 'ui.studio.moved');
        $this->assertSame([15, 9, 20], $at());
        $this->assertSame(['move', $pages], [Version::current()->source, Version::current()->pages]);
        $this->assertSame(['op' => 'move', 'page' => 5, 'to' => 15], last($this->app->make(PdfEngine::class)->ops));

        // earlier in the book: C goes to 2, everything from 2 to 19 moves down one; its edit is still waiting
        $this->post(route('studio.projects.move', $c), ['to' => 2]);
        $this->assertSame([16, 10, 2], $at());
        $this->assertTrue($c->fresh()->profile_stale);

        // past the end means the last page; the same place changes nothing
        $this->post(route('studio.projects.move', $b), ['to' => 999]);
        $this->assertSame([15, $pages, 2], $at());
        $versions = Version::count();
        $this->post(route('studio.projects.move', $b), ['to' => $pages]);
        $this->assertSame($versions, Version::count());

        $draft = $this->projectWithPictures(1);
        $this->post(route('studio.projects.move', $draft), ['to' => 3])->assertSessionHas('error.code', 'portfolio_not_in_profile');
    }

    public function test_deleting_an_approved_project_takes_its_page_out_of_the_profile(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->profile();
        $project = $this->projectWithPictures(2);
        $this->post(route('studio.projects.approve', $project), ['at' => 3]);
        $this->assertSame(46, Version::current()->pages);

        $this->delete(route('studio.projects.destroy', $project))->assertRedirect(route('studio.index'));
        $this->assertSame(45, Version::current()->pages);
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Image::count());
    }

    public function test_a_page_without_pictures_cannot_be_approved(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->profile();
        $this->post(route('studio.projects.store'));
        $this->post(route('studio.projects.approve', Project::first()), ['at' => 3])->assertSessionHas('error.code', 'portfolio_no_images');
        $this->assertSame(1, Version::count());
    }

    public function test_the_profile_font_is_uploaded_once_and_used_for_printing(): void
    {
        $this->post(route('studio.login.attempt'), ['password' => 'first-pass-1']);
        $this->get(route('studio.settings'))->assertInertia(fn (Assert $p) => $p->component('Settings')->where('fonts', ['medium' => false, 'bold' => false]));
        $this->assertStringEndsWith('IBMPlexSansArabic-Bold.ttf', Fonts::path('bold'));

        $ttf = UploadedFile::fake()->createWithContent('SFMada-Bold.ttf', "\x00\x01\x00\x00".str_repeat('x', 64));
        $this->post(route('studio.settings.font', 'bold'), ['file' => $ttf])->assertSessionHas('success');
        $this->post(route('studio.settings.font', 'medium'), ['file' => UploadedFile::fake()->create('not-a-font.ttf', 4)])->assertSessionHas('error.code', 'file_type_not_allowed');

        $this->get(route('studio.settings'))->assertInertia(fn (Assert $p) => $p->where('fonts', ['medium' => false, 'bold' => true]));
        $this->assertStringEndsWith('fonts/bold.ttf', Fonts::path('bold'));
        $this->get(route('studio.fonts', 'bold'))->assertOk();
    }
}
