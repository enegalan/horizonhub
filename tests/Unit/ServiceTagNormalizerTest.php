<?php

namespace Tests\Unit;

use App\Services\Horizon\ServiceTagNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceTagNormalizerTest extends TestCase
{
    #[Test]
    public function normalize_list_dedupes_and_sorts(): void
    {
        $tags = ServiceTagNormalizer::normalizeList(['Staging', 'production', 'staging', '  ']);

        $this->assertSame(['production', 'staging'], $tags);
    }
}
