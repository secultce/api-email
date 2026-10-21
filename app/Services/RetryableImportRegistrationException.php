<?php

namespace App\Services;

use RuntimeException;

/**
 * Falha ocorrida antes do início de Mail::send().
 * Pode ser reexecutada sem repetir uma tentativa SMTP anterior.
 */
class RetryableImportRegistrationException extends RuntimeException {}
