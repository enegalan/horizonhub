<?php

namespace Tests\Unit;

use App\Support\Horizon\MasterReader;
use Tests\TestCase;

class MasterReaderTest extends TestCase
{
    public function test_find_supervisor_returns_master_and_supervisor(): void
    {
        $groups = MasterReader::groupsFromMastersPayload([
            [
                'name' => 'master-1',
                'status' => 'running',
                'supervisors' => [
                    [
                        'name' => 'supervisor-1',
                        'status' => 'running',
                        'options' => ['queue' => 'default'],
                    ],
                ],
            ],
        ]);

        $match = MasterReader::findSupervisor($groups, 'supervisor-1');

        $this->assertNotNull($match);
        $this->assertSame('master-1', $match['master']['name']);
        $this->assertSame('supervisor-1', $match['supervisor']['name']);
        $this->assertNull(MasterReader::findSupervisor($groups, 'missing'));
    }

    public function test_supervisors_from_masters_payload_reuses_group_normalizer(): void
    {
        $masters = [
            [
                'name' => 'master-1',
                'status' => 'running',
                'environment' => 'production',
                'pid' => '10',
                'supervisors' => [
                    [
                        'name' => 'group-a:worker',
                        'status' => 'running',
                        'connection' => 'redis',
                        'processes' => ['default' => 2, 'emails' => 1],
                        'options' => [
                            'connection' => 'redis',
                            'queue' => ['default', 'emails'],
                            'balance' => 'auto',
                        ],
                    ],
                ],
            ],
        ];

        $groups = MasterReader::groupsFromMastersPayload($masters);
        $supervisors = MasterReader::supervisorsFromMastersPayload($masters);

        $this->assertCount(1, $groups);
        $this->assertCount(1, $groups[0]['supervisors']);
        $this->assertCount(1, $supervisors);
        $this->assertSame('group-a:worker', $supervisors[0]['name']);
        $this->assertSame('group-a', $supervisors[0]['groupName']);
        $this->assertSame('redis', $supervisors[0]['connection']);
        $this->assertSame(3, $supervisors[0]['processes']);
        $this->assertSame('auto', $supervisors[0]['balancing']);
        $this->assertSame('running', $supervisors[0]['apiStatus']);
        $this->assertSame(['default', 'emails'], $supervisors[0]['queueNames']);
        $this->assertSame($groups[0]['supervisors'][0]['queues'], $supervisors[0]['queues']);
    }
}
