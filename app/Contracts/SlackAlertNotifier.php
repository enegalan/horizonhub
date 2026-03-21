<?php

namespace App\Contracts;

/**
 * Contract for Slack-based alert notifications.
 * Implementations are bound in the container for AlertEngine injection.
 */
interface SlackAlertNotifier extends AlertNotifier {}
