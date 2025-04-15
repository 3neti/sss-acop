<?php

namespace App\KYC\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Exception;

class FaceVerificationFailedException extends Exception
{
    public function __construct(
        public readonly string $reason = 'Unmatched face.',
        public $code = Response::HTTP_FORBIDDEN
    ) {
        parent::__construct('Face verification failed.', $code);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'reason' => $this->reason,
        ];
    }
}
