<?php
/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Deck\Reference;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\Label;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IURLGenerator;

class CardReferenceProvider implements IReferenceProvider {
	private CardService $cardService;
	private IURLGenerator $urlGenerator;
	private BoardService $boardService;
	private StackService $stackService;
	private ?string $userId;

	public function __construct(CardService $cardService,
								BoardService $boardService,
								StackService $stackService,
								IURLGenerator $urlGenerator,
								?string $userId) {
		$this->cardService = $cardService;
		$this->urlGenerator = $urlGenerator;
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->userId = $userId;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example: https://nextcloud.local/index.php/apps/deck/#/board/2/card/11
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/#\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$ids = $this->getBoardCardId($referenceText);
			if ($ids !== null) {
				[$boardId, $cardId] = $ids;
				$card = $this->cardService->find((int) $cardId)->jsonSerialize();
				$board = $this->boardService->find((int) $boardId)->jsonSerialize();
				$stack = $this->stackService->find((int) $card['stackId'])->jsonSerialize();

				$card = $this->sanitizeSerializedCard($card);
				$board = $this->sanitizeSerializedBoard($board);
				$stack = $this->sanitizeSerializedStack($stack);
				/** @var IReference $reference */
				$reference = new Reference($referenceText);
				$reference->setRichObject(Application::APP_ID . '-card', [
					'id' => $boardId . '/' . $cardId,
					'card' => $card,
					'board' => $board,
					'stack' => $stack,
				]);
				return $reference;
			}
		}

		return null;
	}

	private function sanitizeSerializedStack(array $stack): array {
		$stack['cards'] = array_map(function (CardDetails $cardDetails) {
			$result = $cardDetails->jsonSerialize();
			unset($result['assignedUsers']);
			return $result;
		}, $stack['cards']);

		return $stack;
	}

	private function sanitizeSerializedBoard(array $board): array {
		unset($board['labels']);
		$board['owner'] = $board['owner']->jsonSerialize();
		unset($board['acl']);
		unset($board['users']);

		return $board;
	}

	private function sanitizeSerializedCard(array $card): array {
		$card['labels'] = array_map(function (Label $label) {
			return $label->jsonSerialize();
		}, $card['labels']);
		$card['assignedUsers'] = array_map(function (Assignment $assignment) {
			$result = $assignment->jsonSerialize();
			$result['participant'] = $result['participant']->jsonSerialize();
			return $result;
		}, $card['assignedUsers']);
		$card['owner'] = $card['owner']->jsonSerialize();
		unset($card['relatedStack']);
		unset($card['relatedBoard']);
		$card['attachments'] = array_map(function (Attachment $attachment) {
			return $attachment->jsonSerialize();
		}, $card['attachments']);

		return $card;
	}

	private function getBoardCardId(string $url): ?array {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/#\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches);
		if ($matches && count($matches) > 2) {
			return [$matches[1], $matches[2]];
		}

		preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches2);
		if ($matches2 && count($matches2) > 2) {
			return [$matches2[1], $matches2[2]];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		$ids = $this->getBoardCardId($referenceId);
		if ($ids !== null) {
			[$boardId, $cardId] = $ids;
			return $boardId . '/' . $cardId;
		}

		return $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}
}
