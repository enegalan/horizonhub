<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobSection: string
{
    use HasOptions;

    case Failed = 'failed';
    case Processed = 'processed';
    case Processing = 'processing';

    /**
     * Labels for the section headers.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Processing->value => 'Processing',
            self::Processed->value => 'Processed',
            self::Failed->value => 'Failed',
        ];
    }

    /**
     * Resolve a raw section key, falling back to Processing.
     */
    public static function normalize(mixed $raw): self
    {
        return self::tryFrom((string) $raw) ?? self::Processing;
    }

    /**
     * Badge tone for the section counter.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Processing => 'badge-warning',
            self::Processed => 'badge-success',
            self::Failed => 'badge-danger',
        };
    }

    /**
     * Left border accent, used on the collapsed section.
     */
    public function borderClass(): string
    {
        return match ($this) {
            self::Processing => 'border-l-amber-500/40 hover:border-l-amber-500/60',
            self::Processed => 'border-l-emerald-500/40 hover:border-l-emerald-500/60',
            self::Failed => 'border-l-destructive/40 hover:border-l-destructive/60',
        };
    }

    /**
     * Table columns specific to this section, in render order.
     *
     * @return array<int, array{column: string, label: string, class?: string}>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Processing => [
                ['column' => 'delayed_until', 'label' => 'Delayed until', 'class' => 'min-w-[100px]'],
            ],
            self::Processed => [
                ['column' => 'processed', 'label' => 'Processed', 'class' => 'min-w-[100px]'],
                ['column' => 'runtime', 'label' => 'Runtime', 'class' => 'min-w-[100px]'],
            ],
            self::Failed => [
                ['column' => 'failed_at', 'label' => 'Failed at', 'class' => 'min-w-[100px]'],
                ['column' => 'runtime', 'label' => 'Runtime', 'class' => 'min-w-[100px]'],
            ],
        };
    }

    /**
     * Empty-state copy for this section.
     *
     * @return array<string, string>
     */
    public function emptyCopy(): array
    {
        return match ($this) {
            self::Processing => [
                'title' => 'No processing jobs',
                'description' => 'Jobs currently being executed will appear here.',
            ],
            self::Processed => [
                'title' => 'No processed jobs',
                'description' => 'Completed jobs will appear here.',
            ],
            self::Failed => [
                'title' => 'No failed jobs',
                'description' => 'Failed jobs will appear here.',
            ],
        };
    }

    /**
     * Accent applied while the section is expanded.
     */
    public function openAccentClass(): string
    {
        return match ($this) {
            self::Processing => 'group-open:border-l-amber-500/60 group-open:bg-amber-500/5',
            self::Processed => 'group-open:border-l-emerald-500/60 group-open:bg-emerald-500/5',
            self::Failed => 'group-open:border-l-destructive/60 group-open:bg-destructive/5',
        };
    }

    /**
     * Number of skeleton columns when the service column is hidden.
     */
    public function skeletonColumns(): int
    {
        return \count($this->columns()) + 6;
    }
}
