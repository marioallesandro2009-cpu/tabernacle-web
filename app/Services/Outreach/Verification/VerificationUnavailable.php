<?php

namespace App\Services\Outreach\Verification;

use RuntimeException;

/** The verification service could not give an answer (no credits left, wrong API key, network). */
class VerificationUnavailable extends RuntimeException {}
