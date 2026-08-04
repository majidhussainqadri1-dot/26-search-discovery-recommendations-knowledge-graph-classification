<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;

final class EligibilityEvaluator
{
    public function canView(SearchDocument $document, AudienceContext $audience): bool
    {
        if (in_array($document->state(), ['retracted', 'suspended'], true)) {
            return false;
        }

        $visibility = $document->visibility();
        if ($visibility->isPublic()) {
            return in_array($document->state(), ['published', 'corrected'], true);
        }

        if (! $audience->isAuthenticated()) {
            return false;
        }

        foreach ($visibility->requiredCapabilities() as $capability) {
            if (! $audience->hasCapability($capability)) {
                return false;
            }
        }

        $entitlement = $visibility->requiredEntitlement();
        if ($entitlement !== null && ! $audience->hasEntitlement($entitlement)) {
            return false;
        }

        $minimumAge = $visibility->minimumAge();
        if ($minimumAge !== null && ($audience->age() === null || $audience->age() < $minimumAge)) {
            return false;
        }

        if ($visibility->guardianConsentRequired() && ! $audience->hasVerifiedGuardianConsent()) {
            return false;
        }

        return in_array($document->state(), ['published', 'corrected', 'restricted'], true);
    }
}
