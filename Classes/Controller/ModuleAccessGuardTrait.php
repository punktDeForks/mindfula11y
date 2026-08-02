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
 * The module-access gate of the extension's AJAX controllers.
 *
 * Consuming classes must inject a PermissionService as `$permissionService`
 * and also use JsonErrorResponseTrait for the response shape. Kept apart from
 * DemandSessionGuardTrait so each trait's collaborator requirement is one a
 * consumer either satisfies or does not use at all — bundling both left
 * controllers carrying a guard they could never call.
 */
trait ModuleAccessGuardTrait
{
    /**
     * Returns a 403 response if the current backend user lacks module access, null otherwise.
     *
     * Module access is the defense-in-depth gate behind the endpoints (the
     * ticket endpoint enforces it inside
     * StructureAnalysisAuthorizationService::authorizePage() instead).
     */
    private function requireModuleAccess(): ?ResponseInterface
    {
        if ($this->permissionService->checkModuleAccess()) {
            return null;
        }
        return $this->errorResponse('error.forbidden', 403);
    }
}
