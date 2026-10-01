<?php

namespace Tests\Unit;

use App\Enums\JobSection;
use Tests\TestCase;

class JobSectionTest extends TestCase
{
    public function test_columns_match_the_previous_hardcoded_table_headers(): void
    {
        $this->assertSame(
            [['column' => 'delayed_until', 'label' => 'Delayed until', 'class' => 'min-w-[100px]']],
            JobSection::Processing->columns(),
        );
        $this->assertSame(
            [
                ['column' => 'processed', 'label' => 'Processed', 'class' => 'min-w-[100px]'],
                ['column' => 'runtime', 'label' => 'Runtime', 'class' => 'min-w-[100px]'],
            ],
            JobSection::Processed->columns(),
        );
        $this->assertSame(
            [
                ['column' => 'failed_at', 'label' => 'Failed at', 'class' => 'min-w-[100px]'],
                ['column' => 'runtime', 'label' => 'Runtime', 'class' => 'min-w-[100px]'],
            ],
            JobSection::Failed->columns(),
        );
    }

    public function test_empty_copy_is_section_specific(): void
    {
        $this->assertSame('No processing jobs', JobSection::Processing->emptyCopy()['title']);
        $this->assertSame('No processed jobs', JobSection::Processed->emptyCopy()['title']);
        $this->assertSame('No failed jobs', JobSection::Failed->emptyCopy()['title']);
    }

    public function test_every_section_has_distinct_badge_tone(): void
    {
        $badges = \array_map(fn (JobSection $s) => $s->badgeClass(), JobSection::cases());

        $this->assertSame($badges, \array_unique($badges));
    }

    public function test_exposes_the_three_rendered_sections(): void
    {
        $values = JobSection::values();
        \sort($values);
        $this->assertSame(['failed', 'processed', 'processing'], $values);
        $this->assertSame(['processing' => 'Processing', 'processed' => 'Processed', 'failed' => 'Failed'], JobSection::labels());
    }

    public function test_normalize_falls_back_to_processing(): void
    {
        $this->assertSame(JobSection::Failed, JobSection::normalize('failed'));
        $this->assertSame(JobSection::Processing, JobSection::normalize('nope'));
        $this->assertSame(JobSection::Processing, JobSection::normalize(null));
    }

    public function test_skeleton_columns_count_shared_and_section_columns(): void
    {
        $this->assertSame(8, JobSection::Processing->skeletonColumns() + 1);
        $this->assertSame(9, JobSection::Processed->skeletonColumns() + 1);
        $this->assertSame(9, JobSection::Failed->skeletonColumns() + 1);
    }
}
