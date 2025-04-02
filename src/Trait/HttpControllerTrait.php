<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait HttpControllerTrait
{
    #[Required]
    public NormalizerInterface $normalizer;

    private function successResponse(mixed $data, array $groups = ['default']): JsonResponse
    {
        return new JsonResponse(
            $this->normalizer->normalize($data, context: ['groups' => $groups])
        );
    }
}