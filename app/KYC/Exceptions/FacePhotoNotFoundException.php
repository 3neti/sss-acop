<?php

namespace App\KYC\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FacePhotoNotFoundException extends NotFoundHttpException
{
    public function __construct(string $message = 'No stored photo found for user.', \Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
