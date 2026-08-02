<?php
declare(strict_types=1);

/*
 * Mindful A11y extension for TYPO3 integrating accessibility tools into the backend.
 * Copyright (C) 2026  Mindful Markup, Felix Spittel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MindfulMarkup\MindfulA11y\Controller;

use Psr\Http\Message\ResponseInterface;

/**
 * Pinning a signed demand to the current session.
 *
 * Consuming classes must inject both a PermissionService ($permissionService)
 * and a BackendUserProvider ($backendUserProvider), and also use
 * JsonErrorResponseTrait for the response shape. Only the demand-redeeming
 * endpoints need this; see ModuleAccessGuardTrait for the gate they all share.
 */
trait DemandSessionGuardTrait
{
    /**
     * Verify a signed demand belongs to the current session: same user, same
     * workspace, and access to the demanded language.
     *
     * The redemption-side counterpart of the "signed => authorized at
     * issuance" invariant — a demand is only redeemable in the session scope
     * it was signed for, so the pinning rules must not drift between the
     * demand-redeeming endpoints.
     */
    private function requireDemandSession(int $userId, int $workspaceId, int $languageId): ?ResponseInterface
    {
        $backendUser = $this->backendUserProvider->getAuthenticated();

        if ($backendUser === null || (int)($backendUser->user['uid'] ?? 0) !== $userId) {
            return $this->errorResponse('error.invalidUser', 403);
        }

        if ($backendUser->workspace !== $workspaceId) {
            return $this->errorResponse('error.invalidWorkspace', 403);
        }

        if (!$this->permissionService->checkLanguageAccess($languageId)) {
            return $this->errorResponse('error.invalidLanguage', 403);
        }

        return null;
    }
}
