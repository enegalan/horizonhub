<?php

namespace App\Contracts;

/**
 * Contract for email-based alert notifications.
 * Implementations are bound in the container for AlertEngine injection.
 */
interface EmailAlertNotifier extends AlertNotifier {}
