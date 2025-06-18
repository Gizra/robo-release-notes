<?php

namespace Gizra\RoboReleaseNotes\Tests\Fixtures;

use Gizra\RoboReleaseNotes\ReleaseNotesGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for different release history scenarios.
 */
class ReleaseHistoryTest extends TestCase
{
    /**
     * Mock task context.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $mockTask;

    /**
     * Release notes generator instance.
     *
     * @var \Gizra\RoboReleaseNotes\ReleaseNotesGenerator
     */
    private $generator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTask = $this->createMock(\Robo\Contract\TaskInterface::class);
        $this->generator = new ReleaseNotesGenerator($this->mockTask);
    }

    /**
     * Test release history with multiple features and bug fixes.
     */
    public function testComplexReleaseHistory(): void
    {
        $method = $this->getPrivateMethod('groupChangesByIssue');

        // Simulate a complex release with multiple features and fixes.
        $releaseData = [
            'pull_requests' => [
                '101' => (object) [
                    'title' => 'Implement user authentication system',
                    'body' => 'Implements #200 - Add OAuth2 support',
                    'head' => (object) ['ref' => 'feature/200-oauth'],
                    'user' => (object) ['login' => 'alice'],
                    'additions' => 150,
                    'deletions' => 20,
                    'changed_files' => 8,
                ],
                '102' => (object) [
                    'title' => 'Add password reset functionality',
                    'body' => 'Fixes #201 - Users cannot reset passwords',
                    'head' => (object) ['ref' => 'fix/201-password-reset'],
                    'user' => (object) ['login' => 'bob'],
                    'additions' => 75,
                    'deletions' => 10,
                    'changed_files' => 3,
                ],
                '103' => (object) [
                    'title' => 'Update user interface styling',
                    'body' => 'Resolves #202 - Improve UI consistency',
                    'head' => (object) ['ref' => 'ui/202-styling'],
                    'user' => (object) ['login' => 'charlie'],
                    'additions' => 200,
                    'deletions' => 50,
                    'changed_files' => 12,
                ],
                '104' => (object) [
                    'title' => 'Add database migrations',
                    'body' => 'Related to #200 - Database schema for OAuth',
                    'head' => (object) ['ref' => 'db/oauth-schema'],
                    'user' => (object) ['login' => 'alice'],
                    'additions' => 30,
                    'deletions' => 0,
                    'changed_files' => 2,
                ],
                '105' => (object) [
                    'title' => 'Fix typo in documentation',
                    'body' => 'Minor documentation fix',
                    'head' => (object) ['ref' => 'docs/typo-fix'],
                    'user' => (object) ['login' => 'david'],
                    'additions' => 2,
                    'deletions' => 2,
                    'changed_files' => 1,
                ],
            ],
            'issues' => [
                '200' => (object) [
                    'title' => 'Add OAuth2 authentication support',
                    'user' => (object) ['login' => 'product-manager'],
                ],
                '201' => (object) [
                    'title' => 'Users cannot reset passwords',
                    'user' => (object) ['login' => 'user-reporter'],
                ],
                '202' => (object) [
                    'title' => 'Improve UI consistency across pages',
                    'user' => (object) ['login' => 'designer'],
                ],
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$releaseData]);
        
        // Verify proper grouping.
        $this->assertArrayHasKey('with_issues', $result);
        $this->assertArrayHasKey('without_issues', $result);
        
        // Issue #200 should have 2 PRs (101 and 104).
        $this->assertArrayHasKey('200', $result['with_issues']);
        $this->assertCount(2, $result['with_issues']['200']);
        $this->assertContains('101', $result['with_issues']['200']);
        $this->assertContains('104', $result['with_issues']['200']);
        
        // Issue #201 should have 1 PR (102).
        $this->assertArrayHasKey('201', $result['with_issues']);
        $this->assertCount(1, $result['with_issues']['201']);
        $this->assertContains('102', $result['with_issues']['201']);
        
        // Issue #202 should have 1 PR (103).
        $this->assertArrayHasKey('202', $result['with_issues']);
        $this->assertCount(1, $result['with_issues']['202']);
        $this->assertContains('103', $result['with_issues']['202']);
        
        // PR #105 should be without issues.
        $this->assertContains('105', $result['without_issues']);
    }

    /**
     * Test release history with hotfix scenario.
     */
    public function testHotfixReleaseHistory(): void
    {
        $method = $this->getPrivateMethod('extractPrNumbers');

        // Simulate hotfix commits.
        $commits = [
            [
                'hash' => 'hotfix123',
                'subject' => 'Hotfix: Critical security vulnerability (#999)',
                'body' => 'Emergency fix for CVE-2023-1234',
            ],
            [
                'hash' => 'hotfix124',
                'subject' => 'Merge pull request #1000 from security/urgent-patch',
                'body' => 'Apply security patch immediately',
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$commits]);
        
        $this->assertContains('999', $result);
        $this->assertContains('1000', $result);
    }

    /**
     * Test release history with feature branch workflow.
     */
    public function testFeatureBranchWorkflow(): void
    {
        $method = $this->getPrivateMethod('extractIssueNumbers');

        // Test feature branch with multiple related issues.
        $prData = (object) [
            'title' => 'Epic: Complete payment system overhaul',
            'body' => 'This epic includes:\n- Fixes #301 (payment gateway)\n- Closes #302 (transaction logging)\n- Resolves #303 (currency support)\n- Implements #304 (refund system)',
            'head' => (object) ['ref' => 'epic/300-payment-overhaul'],
        ];

        $result = $method->invokeArgs($this->generator, [$prData]);
        
        $this->assertContains('301', $result);
        $this->assertContains('302', $result);
        $this->assertContains('303', $result);
        $this->assertContains('304', $result);
        $this->assertContains('300', $result); // From branch name.
    }

    /**
     * Test release history with different merge strategies.
     */
    public function testMergeStrategies(): void
    {
        $method = $this->getPrivateMethod('extractPrNumbers');

        $commits = [
            // Standard merge commit.
            [
                'hash' => 'merge1',
                'subject' => 'Merge pull request #150 from feature/user-profiles',
                'body' => 'Add user profile management',
            ],
            // Squash and merge.
            [
                'hash' => 'squash1',
                'subject' => 'Add user profile management (#151)',
                'body' => 'Squashed commit of multiple changes',
            ],
            // Rebase and merge (no explicit merge commit).
            [
                'hash' => 'rebase1',
                'subject' => 'Add profile photo upload feature',
                'body' => 'Related to #152',
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$commits]);
        
        $this->assertContains('150', $result);
        $this->assertContains('151', $result);
        $this->assertContains('152', $result);
    }

    /**
     * Test release history with dependency updates.
     */
    public function testDependencyUpdates(): void
    {
        $method = $this->getPrivateMethod('extractPrNumbers');

        // Simulate dependency update commits.
        $commits = [
            [
                'hash' => 'deps1',
                'subject' => 'Bump lodash from 4.17.15 to 4.17.21 (#400)',
                'body' => 'Bumps lodash from 4.17.15 to 4.17.21.',
            ],
            [
                'hash' => 'deps2',
                'subject' => 'Update symfony/console requirement from ^4.0 to ^5.0 (#401)',
                'body' => 'Updates the requirements on symfony/console',
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$commits]);
        
        $this->assertContains('400', $result);
        $this->assertContains('401', $result);
    }

    /**
     * Test release history with no clear PR pattern.
     */
    public function testDirectCommits(): void
    {
        $method = $this->getPrivateMethod('extractPrNumbers');

        // Simulate direct commits without PR workflow.
        $commits = [
            [
                'hash' => 'direct1',
                'subject' => 'Fix spelling mistakes in documentation',
                'body' => 'Various typo fixes',
            ],
            [
                'hash' => 'direct2',
                'subject' => 'Update version number to 2.1.0',
                'body' => 'Prepare for release',
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$commits]);
        
        // Should return empty array as no PR patterns found.
        $this->assertEquals([], $result);
    }

    /**
     * Test release history with mixed commit types.
     */
    public function testMixedCommitTypes(): void
    {
        $method = $this->getPrivateMethod('extractPrNumbers');

        $commits = [
            // PR merge.
            [
                'hash' => 'pr1',
                'subject' => 'Merge pull request #500 from feature/api-v2',
                'body' => 'Add API v2 endpoints',
            ],
            // Direct commit.
            [
                'hash' => 'direct1',
                'subject' => 'Fix typo in API documentation',
                'body' => 'Quick fix',
            ],
            // Squash merge.
            [
                'hash' => 'squash1',
                'subject' => 'Implement rate limiting (#501)',
                'body' => 'Add rate limiting to API endpoints',
            ],
            // Another direct commit.
            [
                'hash' => 'direct2',
                'subject' => 'Update changelog',
                'body' => 'Add entries for v2.2.0',
            ],
        ];

        $result = $method->invokeArgs($this->generator, [$commits]);
        
        // Should only extract PR numbers from PR-related commits.
        $this->assertContains('500', $result);
        $this->assertContains('501', $result);
        $this->assertCount(2, $result);
    }

    /**
     * Get a private method for testing.
     *
     * @param string $methodName
     *   The method name to get.
     *
     * @return \ReflectionMethod
     *   The reflection method.
     */
    private function getPrivateMethod(string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($this->generator);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}