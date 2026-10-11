<?php

namespace Tests\Unit;

use App\Support\Services\ServiceTagNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceTagNormalizerTest extends TestCase
{
    #[Test]
    public function from_json_dedupes_and_sorts_the_payload(): void
    {
        $tags = ServiceTagNormalizer::fromJson('["Staging","production","staging"]');

        $this->assertSame(['production', 'staging'], $tags);
    }

    #[Test]
    public function from_json_memoizes_the_payload(): void
    {
        $first = ServiceTagNormalizer::fromJson('["b","a"]');
        $second = ServiceTagNormalizer::fromJson('["b","a"]');

        $this->assertSame(['a', 'b'], $first);
        $this->assertSame($first, $second);
    }

    #[Test]
    public function from_json_returns_an_empty_list_for_malformed_payloads(): void
    {
        $this->assertSame([], ServiceTagNormalizer::fromJson('not-json'));
        $this->assertSame([], ServiceTagNormalizer::fromJson('"production"'));
    }

    #[Test]
    public function from_json_returns_an_empty_list_for_null_and_empty_payloads(): void
    {
        $this->assertSame([], ServiceTagNormalizer::fromJson(null));
        $this->assertSame([], ServiceTagNormalizer::fromJson(''));
    }

    #[Test]
    public function normalize_list_dedupes_and_sorts(): void
    {
        $tags = ServiceTagNormalizer::normalize(['Staging', 'production', 'staging', '  ']);

        $this->assertSame(['production', 'staging'], $tags);
    }
}
