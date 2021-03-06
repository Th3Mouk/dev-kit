<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DevKit;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class GithubHookProcessor
{
    /**
     * @var \Github\Client
     */
    private $githubClient;

    /**
     * @param string|null $githubAuthKey
     */
    public function __construct($githubAuthKey = null)
    {
        $this->githubClient = new \Github\Client();

        if ($githubAuthKey) {
            $this->githubClient->authenticate($githubAuthKey, null, \Github\Client::AUTH_HTTP_TOKEN);
        }
    }

    /**
     * Removes the "Pending Author" label if the author respond on an issue or pull request.
     *
     * Github events: issue_comment, pull_request_review_comment
     *
     * @param string $eventName
     * @param array  $payload
     */
    public function processPendingAuthor($eventName, array $payload)
    {
        if (!in_array($payload['action'], array('created', 'synchronize'), true)) {
            return;
        }

        $issueKey = 'issue_comment' === $eventName ? 'issue' : 'pull_request';

        list($repoUser, $repoName) = explode('/', $payload['repository']['full_name']);
        $issueId = $payload[$issueKey]['number'];
        $issueAuthorId = $payload[$issueKey]['user']['id'];
        // If it's a PR synchronization, it's obviously done from the author.
        $commentAuthorId = 'synchronize' === $payload['action'] ? $issueAuthorId : $payload['comment']['user']['id'];

        if ($commentAuthorId === $issueAuthorId) {
            $this->removeIssueLabel($repoUser, $repoName, $issueId, 'pending author');
        }
    }

    /**
     * Manages RTM and 'review required' labels.
     *
     * - If a PR is opened or updated, 'review required' is set.
     * - If a PR is updated and 'RTM' is set, it is removed.
     *
     * @param string $eventName
     * @param array  $payload
     */
    public function processReviewLabels($eventName, array $payload)
    {
        if (!in_array($payload['action'], array('opened', 'synchronize'), true)) {
            return;
        }

        list($repoUser, $repoName) = explode('/', $payload['repository']['full_name']);
        // Add the label for opened and synchronized PRs.
        $this->addIssueLabel($repoUser, $repoName, $payload['number'], 'review required');

        if ('synchronize' === $payload['action']) {
            $this->removeIssueLabel($repoUser, $repoName, $payload['number'], 'RTM');
        }
    }

    /**
     * Adds a label from an issue if this one is not set.
     *
     * @param string $repoUser
     * @param string $repoName
     * @param int    $issueId
     * @param string $label
     */
    private function addIssueLabel($repoUser, $repoName, $issueId, $label)
    {
        foreach ($this->githubClient->issues()->labels()->all($repoUser, $repoName, $issueId) as $labelInfo) {
            if ($label === $labelInfo['name']) {
                return;
            }
        }

        $this->githubClient->issues()->labels()->add($repoUser, $repoName, $issueId, $label);
    }

    /**
     * Removes a label from an issue if this one is set.
     *
     * @param string $repoUser
     * @param string $repoName
     * @param int    $issueId
     * @param string $label
     */
    private function removeIssueLabel($repoUser, $repoName, $issueId, $label)
    {
        foreach ($this->githubClient->issues()->labels()->all($repoUser, $repoName, $issueId) as $labelInfo) {
            if ($label === $labelInfo['name']) {
                $this->githubClient->issues()->labels()->remove($repoUser, $repoName, $issueId, $label);
                break;
            }
        }
    }
}
