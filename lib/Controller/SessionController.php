<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\SessionService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Db\BoardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCA\Deck\Db\Acl;

class SessionController extends OCSController {
	private SessionService $sessionService;
	private PermissionService $permissionService;
	private BoardMapper $boardMapper;

	public function __construct($appName,
		IRequest $request,
		SessionService $sessionService,
		PermissionService $permissionService,
		BoardMapper $boardMapper
	) {
		parent::__construct($appName, $request);
		$this->sessionService = $sessionService;
		$this->permissionService = $permissionService;
		$this->boardMapper = $boardMapper;
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(int $boardId): DataResponse {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);

		$session = $this->sessionService->initSession($boardId);
		return new DataResponse([
			'token' => $session->getToken(),
		]);
	}

	/**
	 * notifies the server that the session is still active
	 * @NoAdminRequired
	 * @param $boardId
	 */
	public function sync(int $boardId, string $token): DataResponse {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		try {
			$this->sessionService->syncSession($boardId, $token);
			return new DataResponse([]);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], 404);
		}
	}

	/**
	 * delete a session if existing
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param $boardId
	 */
	public function close(int $boardId, string $token) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$this->sessionService->closeSession($boardId, $token);
		return new DataResponse();
	}
}
