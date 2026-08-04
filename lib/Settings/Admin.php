<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch\Settings;

use InvalidArgumentException;
use OCA\FullTextSearch\AppInfo\Application;
use OCA\FullTextSearch\ConfigLexicon;
use OCA\FullTextSearch\Model\PlatformWrapper;
use OCA\FullTextSearch\Service\ConfigService;
use OCA\FullTextSearch\Service\PlatformService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsFormWithHandlers;

class Admin implements IDeclarativeSettingsFormWithHandlers {
	private const FIELDS = [
		ConfigLexicon::SEARCH_PLATFORM,
		ConfigLexicon::APP_NAVIGATION,
	];

	public function __construct(
		private readonly ConfigService $configService,
		private readonly PlatformService $platformService,
		private readonly IL10N $l10n,
	) {
	}

	#[\Override]
	public function getSchema(): array {
		$platformNames = array_map(
			static fn (PlatformWrapper $wrapper): string => $wrapper->getPlatform()->getName(),
			$this->platformService->getPlatforms(),
		);

		return [
			'id' => 'general',
			'priority' => 0,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => Application::APP_ID,
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL,
			'title' => $this->l10n->t('General'),
			'description' => $this->l10n->t('Configure the full-text search framework.'),
			'doc_url' => 'https://github.com/nextcloud/fulltextsearch/wiki',
			'fields' => [
				[
					'id' => ConfigLexicon::SEARCH_PLATFORM,
					'title' => $this->l10n->t('Search Platform'),
					'description' => $this->l10n->t('Select the app to index content and answer search queries.'),
					'type' => DeclarativeSettingsTypes::SELECT,
					'options' => $platformNames,
					'placeholder' => $this->l10n->t('No search platform selected'),
					'default' => '',
				],
				[
					'id' => ConfigLexicon::APP_NAVIGATION,
					'title' => $this->l10n->t('Navigation Icon'),
					'label' => $this->l10n->t('Enable global search within all your content'),
					'description' => $this->l10n->t('Show the full-text search entry in the main navigation.'),
					'type' => DeclarativeSettingsTypes::CHECKBOX,
					'default' => false,
				],
			],
		];
	}

	#[\Override]
	public function getValue(string $fieldId, IUser $user): mixed {
		$this->assertKnownField($fieldId);
		$value = $this->configService->getConfig()[$fieldId];

		if ($fieldId !== ConfigLexicon::SEARCH_PLATFORM || $value === '') {
			return $value;
		}

		foreach ($this->platformService->getPlatforms() as $wrapper) {
			if ($wrapper->getClass() === $value) {
				return $wrapper->getPlatform()->getName();
			}
		}

		return '';
	}

	#[\Override]
	public function setValue(string $fieldId, mixed $value, IUser $user): void {
		$this->assertKnownField($fieldId);

		if ($fieldId === ConfigLexicon::APP_NAVIGATION) {
			$this->configService->setConfig([$fieldId => $this->normalizeBoolean($value)]);
			return;
		}

		if (!is_string($value)) {
			throw new InvalidArgumentException('Invalid search platform setting');
		}

		if ($value === '') {
			$this->configService->setConfig([$fieldId => '']);
			return;
		}

		foreach ($this->platformService->getPlatforms() as $wrapper) {
			if ($wrapper->getPlatform()->getName() === $value) {
				$this->configService->setConfig([$fieldId => $wrapper->getClass()]);
				return;
			}
		}

		throw new InvalidArgumentException('Unknown search platform');
	}

	private function assertKnownField(string $fieldId): void {
		if (!in_array($fieldId, self::FIELDS, true)) {
			throw new InvalidArgumentException('Unknown settings field: ' . $fieldId);
		}
	}

	private function normalizeBoolean(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'yes', 'on', 'true'], true);
		}

		throw new InvalidArgumentException('Invalid boolean settings value');
	}
}
