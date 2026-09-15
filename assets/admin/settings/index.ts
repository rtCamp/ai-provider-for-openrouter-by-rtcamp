/**
 * OpenRouter Settings Models Script
 *
 * This handles the autocomplete features and model details display
 * on the OpenRouter settings page in the WordPress admin panel.
 */

import './style.scss';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

// Type definitions for OpenRouter Settings structures
interface OpenRouterSettingsI18n {
	loading?: string;
	noResults?: string;
	errorLoad?: string;
	typeMore?: string;
	modelsCount?: string;
	inPrice?: string;
	outPrice?: string;
	ctx?: string;
	perMillion?: string;
	free?: string;
	na?: string;
}

interface OpenRouterSettingsGlobal {
	selectedModel?: string;
	selectedImageModel?: string;
	i18n?: OpenRouterSettingsI18n;
}

declare global {
	interface Window {
		AIProviderForOpenRouterByrtCampSettings?: OpenRouterSettingsGlobal;
	}
}

interface Pricing {
	prompt?: string;
	completion?: string;
	image?: string;
	web_search?: string;
	[key: string]: string | undefined;
}

interface OpenRouterModel {
	id: string;
	name?: string;
	pricing?: Pricing;
	context_length?: number;
}

(function () {
	'use strict';

	// Keep track of the models loaded from the server
	let allModels: OpenRouterModel[] = [];
	let allImageModels: OpenRouterModel[] = [];
	let isLoaded = false;
	let isImageLoaded = false;

	const settings = window.AIProviderForOpenRouterByrtCampSettings || {};
	const i18n = settings.i18n || {};

	/**
	 * Formats per-token or per-unit API pricing into a clean, human-readable label.
	 *
	 * Token pricing is normally multiplied by 1,000,000 so the user sees price per million tokens.
	 * Unit pricing (like images or search requests) is formatted as price per generation or request.
	 *
	 * @param priceStr Raw price string from the OpenRouter API response.
	 * @param key      Optional pricing key (e.g. 'image', 'web_search').
	 * @returns A polished label like "$0.50/1M" or "$0.02 / image".
	 */
	function formatPrice(priceStr: string | undefined, key?: string): string {
		if (!priceStr) {
			return i18n.na || __('N/A', 'rtcamps-ai-provider-for-openrouter');
		}
		const price = parseFloat(priceStr);
		if (isNaN(price)) {
			return i18n.na || __('N/A', 'rtcamps-ai-provider-for-openrouter');
		}
		if (price < 0) {
			return __('Unavailable', 'rtcamps-ai-provider-for-openrouter');
		}
		if (price === 0) {
			return (
				i18n.free || __('Free', 'rtcamps-ai-provider-for-openrouter')
			);
		}

		// Handle visual assets and other absolute pricing units
		if (key === 'image' || key === 'web_search') {
			const unit =
				key === 'image'
					? ' ' + __('/ image', 'rtcamps-ai-provider-for-openrouter')
					: ' ' + __('/ req', 'rtcamps-ai-provider-for-openrouter');
			let formatted = '';
			const exponent = Math.floor(Math.log(price) / Math.LN10);
			if (exponent < 0) {
				const decimals = Math.max(2, -exponent + 2);
				const cappedDecimals = Math.min(14, decimals);
				formatted = price.toFixed(cappedDecimals);
				formatted = formatted.replace(/0+$/, '');
				if (formatted.endsWith('.')) {
					formatted = formatted + '00';
				} else {
					const parts = formatted.split('.');
					if (parts[1] && parts[1].length === 1) {
						formatted = formatted + '0';
					}
				}
			} else {
				formatted = price.toFixed(2);
			}
			return '$' + formatted + unit;
		}

		// Standard pricing is per million tokens
		const perMillion = price * 1000000;
		const formatted =
			perMillion >= 1
				? '$' + perMillion.toFixed(2)
				: '$' + perMillion.toPrecision(3);
		return (
			formatted +
			(i18n.perMillion || __('/1M', 'rtcamps-ai-provider-for-openrouter'))
		);
	}

	/**
	 * Formats context numbers to include thousands separators for clean reading.
	 * E.g., 128000 becomes "128,000".
	 *
	 * @param n Context length number.
	 * @returns Formatted context number string.
	 */
	function formatContext(n: number): string {
		return n.toLocaleString();
	}

	/**
	 * Simple HTML escaping utility to protect against basic injection.
	 *
	 * @param str The unsafe text string.
	 * @returns An HTML-safe string.
	 */
	function escapeHtml(str: string): string {
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/**
	 * Extracts provider info for brand styling from model ID.
	 *
	 * @param modelId Model identifier string.
	 * @returns Object with provider display name and CSS class.
	 */
	function getProviderInfo(modelId: string): {
		label: string;
		class: string;
	} {
		const parts = modelId.split('/');
		if (parts.length > 1) {
			const rawProvider = parts[0];
			if (rawProvider !== undefined) {
				const provider = rawProvider.toLowerCase();
				switch (provider) {
					case 'openai':
						return {
							label: 'OpenAI',
							class: 'openrouter-provider-openai',
						};
					case 'anthropic':
						return {
							label: 'Anthropic',
							class: 'openrouter-provider-anthropic',
						};
					case 'google':
						return {
							label: 'Google',
							class: 'openrouter-provider-google',
						};
					case 'meta':
					case 'meta-llama':
						return {
							label: 'Meta',
							class: 'openrouter-provider-meta',
						};
					case 'mistral':
					case 'mistralai':
						return {
							label: 'Mistral',
							class: 'openrouter-provider-mistral',
						};
					case 'deepseek':
						return {
							label: 'DeepSeek',
							class: 'openrouter-provider-deepseek',
						};
					case 'cohere':
						return {
							label: 'Cohere',
							class: 'openrouter-provider-cohere',
						};
					case 'stabilityai':
						return {
							label: 'Stability',
							class: 'openrouter-provider-stability',
						};
					case 'black-forest-labs':
						return {
							label: 'BFL',
							class: 'openrouter-provider-bfl',
						};
					default:
						return {
							label:
								provider.charAt(0).toUpperCase() +
								provider.slice(1),
							class: 'openrouter-provider-generic',
						};
				}
			}
		}
		return { label: 'AI', class: 'openrouter-provider-generic' };
	}

	/**
	 * Highlights the searched query inside the matching string.
	 *
	 * @param text  Full text to search within.
	 * @param query Searching characters.
	 * @returns Highlighted HTML string.
	 */
	function highlightText(text: string, query: string): string {
		if (!query) {
			return escapeHtml(text);
		}
		try {
			const escapedQuery = query.replace(
				/[-\/\\^$*+?.()|[\]{}]/g,
				'\\$&'
			);
			const regex = new RegExp('(' + escapedQuery + ')', 'gi');
			const parts = text.split(regex);
			return parts
				.map((part) =>
					regex.test(part)
						? '<span class="openrouter-highlight">' +
							escapeHtml(part) +
							'</span>'
						: escapeHtml(part)
				)
				.join('');
		} catch (e) {
			return escapeHtml(text);
		}
	}

	/**
	 * Renders up to 5 model suggestions into the autocomplete dropdown menu.
	 *
	 * @param matches     Filtered list of models matching search keywords.
	 * @param dropdown    Dropdown DOM element.
	 * @param searchInput Autocomplete text input.
	 * @param hiddenInput Hidden field storing final saved model name.
	 * @param infoEl      Block underneath the input displaying selection details.
	 * @param queryText   Search query term to highlight.
	 */
	function renderDropdown(
		matches: OpenRouterModel[],
		dropdown: HTMLElement,
		searchInput: HTMLInputElement,
		hiddenInput: HTMLInputElement,
		infoEl: HTMLElement,
		queryText: string = ''
	): void {
		dropdown.innerHTML = '';

		if (matches.length === 0) {
			dropdown.style.display = 'none';
			return;
		}

		matches.forEach(function (model) {
			const modelId = typeof model.id === 'string' ? model.id : '';
			if (!modelId) {
				return;
			}

			const pricing = model.pricing || {};
			const inputPrice = formatPrice(pricing.prompt);
			const outputPrice = formatPrice(pricing.completion);
			const ctxText =
				model.context_length && typeof model.context_length === 'number'
					? formatContext(model.context_length) +
						' ' +
						(i18n.ctx ||
							__('ctx', 'rtcamps-ai-provider-for-openrouter'))
					: '';
			const nameDisplay =
				model.name && model.name !== modelId ? model.name : '';

			const provider = getProviderInfo(modelId);

			const item = document.createElement('div');
			item.setAttribute('role', 'option');
			item.className = 'openrouter-dropdown-item';
			item.innerHTML =
				'<div class="openrouter-dropdown-item-header">' +
				'<strong class="openrouter-dropdown-item-id">' +
				highlightText(modelId, queryText) +
				'</strong>' +
				'<span class="openrouter-provider-tag ' +
				provider.class +
				'">' +
				escapeHtml(provider.label) +
				'</span>' +
				'</div>' +
				(nameDisplay
					? '<span class="openrouter-dropdown-item-name">' +
						escapeHtml(nameDisplay) +
						'</span>'
					: '') +
				'<span class="openrouter-dropdown-item-meta">' +
				escapeHtml(
					i18n.inPrice ||
						__('Prompt:', 'rtcamps-ai-provider-for-openrouter')
				) +
				' <strong class="openrouter-dropdown-item-price-val">' +
				escapeHtml(inputPrice) +
				'</strong>' +
				'&nbsp;&nbsp;' +
				escapeHtml(
					i18n.outPrice ||
						__('Completion:', 'rtcamps-ai-provider-for-openrouter')
				) +
				' <strong class="openrouter-dropdown-item-price-val">' +
				escapeHtml(outputPrice) +
				'</strong>' +
				(ctxText
					? '<span class="openrouter-dropdown-item-ctx">' +
						escapeHtml(ctxText) +
						'</span>'
					: '') +
				'</span>';

			item.addEventListener('mousedown', function (e) {
				// Prevent input blur before click event resolves
				e.preventDefault();
				selectModel(model, searchInput, hiddenInput, dropdown, infoEl);
			});

			dropdown.appendChild(item);
		});

		dropdown.style.display = 'block';
	}

	/**
	 * Commits a model selection: updates the visible input, the hidden value
	 * field, and the info line below the field, then closes the dropdown.
	 *
	 * @param model       Active model object details.
	 * @param searchInput Autocomplete input element.
	 * @param hiddenInput Hidden value storage input element.
	 * @param dropdown    Autocomplete dropdown container.
	 * @param infoEl      Information badge container.
	 */
	function selectModel(
		model: OpenRouterModel,
		searchInput: HTMLInputElement,
		hiddenInput: HTMLInputElement,
		dropdown: HTMLElement,
		infoEl: HTMLElement
	): void {
		const modelId = typeof model.id === 'string' ? model.id : '';
		searchInput.value = modelId;
		hiddenInput.value = modelId;
		dropdown.style.display = 'none';
		renderModelInfo(model, infoEl);
	}

	function formatPricingKey(key: string): string {
		if (key === 'prompt') {
			return (
				i18n.inPrice ||
				__('Prompt:', 'rtcamps-ai-provider-for-openrouter')
			).replace(/:$/, '');
		}
		if (key === 'completion') {
			return (
				i18n.outPrice ||
				__('Completion:', 'rtcamps-ai-provider-for-openrouter')
			).replace(/:$/, '');
		}
		return key
			.split('_')
			.map(function (word) {
				return word.charAt(0).toUpperCase() + word.slice(1);
			})
			.join(' ');
	}

	/**
	 * Renders detailed pricing badges and context limits below the selected input field.
	 *
	 * @param model  Active model object details, or null to clear display.
	 * @param infoEl The information target container.
	 */
	function renderModelInfo(
		model: OpenRouterModel | null,
		infoEl: HTMLElement
	): void {
		if (!model) {
			infoEl.innerHTML = '';
			return;
		}

		const pricing = model.pricing || {};
		const ctxText =
			model.context_length && typeof model.context_length === 'number'
				? formatContext(model.context_length) +
					' ' +
					(i18n.ctx ||
						__('ctx', 'rtcamps-ai-provider-for-openrouter'))
				: '';

		let pricingHtml = '';
		const keys: string[] = [];

		if (pricing.prompt !== undefined) {
			keys.push('prompt');
		}
		if (pricing.completion !== undefined) {
			keys.push('completion');
		}

		Object.keys(pricing).forEach(function (key) {
			if (key !== 'prompt' && key !== 'completion') {
				keys.push(key);
			}
		});

		if (keys.length > 0) {
			pricingHtml = '<div class="openrouter-info-flex">';
			keys.forEach(function (key) {
				const label = formatPricingKey(key);
				const value = formatPrice(pricing[key], key);
				const isFree = value.toLowerCase().includes('free');
				const badgeClass = isFree
					? 'openrouter-info-badge openrouter-info-badge-free'
					: 'openrouter-info-badge';

				pricingHtml +=
					'<span class="' +
					badgeClass +
					'">' +
					escapeHtml(label) +
					': <strong>' +
					escapeHtml(value) +
					'</strong></span>';
			});

			if (ctxText) {
				pricingHtml +=
					'<span class="openrouter-info-ctx">' +
					escapeHtml(ctxText) +
					'</span>';
			}

			// Add direct link to model page on OpenRouter if it is a specific model
			if (model.id && model.id !== 'openrouter/auto') {
				pricingHtml +=
					'<a href="https://openrouter.ai/' +
					escapeHtml(model.id) +
					'" target="_blank" rel="noopener noreferrer" class="openrouter-info-external-link">' +
					escapeHtml(
						__(
							'View on OpenRouter',
							'rtcamps-ai-provider-for-openrouter'
						)
					) +
					'</a>';
			}

			pricingHtml += '</div>';
		} else if (ctxText) {
			pricingHtml =
				'<div class="openrouter-info-simple-ctx">' +
				escapeHtml(ctxText) +
				'</div>';
		}

		infoEl.innerHTML = pricingHtml;
	}

	/**
	 * Filters the list of loaded text models and populates results inside the dropdown.
	 *
	 * @param query       Search query query.
	 * @param dropdown    Dropdown element.
	 * @param searchInput Autocomplete input.
	 * @param hiddenInput Hidden value storage input.
	 * @param infoEl      Information badge container.
	 */
	function filterModels(
		query: string,
		dropdown: HTMLElement,
		searchInput: HTMLInputElement,
		hiddenInput: HTMLInputElement,
		infoEl: HTMLElement
	): void {
		const matches = allModels
			.filter(function (model) {
				const id =
					typeof model.id === 'string' ? model.id.toLowerCase() : '';
				const name =
					typeof model.name === 'string'
						? model.name.toLowerCase()
						: '';
				return id.indexOf(query) !== -1 || name.indexOf(query) !== -1;
			})
			.slice(0, 5);

		renderDropdown(
			matches,
			dropdown,
			searchInput,
			hiddenInput,
			infoEl,
			query
		);
	}

	/**
	 * Filters loaded image generation models based on our active search terms.
	 *
	 * @param query Active search term.
	 * @returns Matches array list of image models.
	 */
	function getImageModelMatches(query: string): OpenRouterModel[] {
		const imageModels = allImageModels;

		if (!query) {
			return imageModels;
		}

		return imageModels.filter(function (model) {
			const id =
				typeof model.id === 'string' ? model.id.toLowerCase() : '';
			const name =
				typeof model.name === 'string' ? model.name.toLowerCase() : '';
			return id.indexOf(query) !== -1 || name.indexOf(query) !== -1;
		});
	}

	/**
	 * Displays list suggestions for OpenRouter models capable of image output.
	 *
	 * @param matches          Matches list to display.
	 * @param dropdown         Dropdown container.
	 * @param imageSearchInput Search input field.
	 * @param imageHiddenInput Hidden saved value field.
	 * @param imageInfoEl      Information badge container.
	 * @param queryText        Query string.
	 */
	function renderImageDropdown(
		matches: OpenRouterModel[],
		dropdown: HTMLElement,
		imageSearchInput: HTMLInputElement,
		imageHiddenInput: HTMLInputElement,
		imageInfoEl: HTMLElement,
		queryText: string = ''
	): void {
		renderDropdown(
			matches,
			dropdown,
			imageSearchInput,
			imageHiddenInput,
			imageInfoEl,
			queryText
		);
	}

	/**
	 * Filters image-generation models and renders appropriate suggestions.
	 *
	 * @param query            Search query query.
	 * @param dropdown         Dropdown element.
	 * @param imageSearchInput Autocomplete input.
	 * @param imageHiddenInput Hidden value storage input.
	 * @param imageInfoEl      Information badge container.
	 */
	function filterImageModels(
		query: string,
		dropdown: HTMLElement,
		imageSearchInput: HTMLInputElement,
		imageHiddenInput: HTMLInputElement,
		imageInfoEl: HTMLElement
	): void {
		renderImageDropdown(
			getImageModelMatches(query).slice(0, 5),
			dropdown,
			imageSearchInput,
			imageHiddenInput,
			imageInfoEl,
			query
		);
	}

	/**
	 * Fetches all available OpenRouter text models via WordPress REST API.
	 * Once loaded, it shows a tally status and highlights details on any pre-saved selection.
	 *
	 * @param savedModel Saved model value.
	 * @param statusEl   Status element container.
	 * @param infoEl     Information details card container.
	 */
	function loadModels(
		savedModel: string,
		statusEl: HTMLElement,
		infoEl: HTMLElement
	): void {
		statusEl.textContent =
			i18n.loading ||
			__('Loading models…', 'rtcamps-ai-provider-for-openrouter');
		statusEl.className = 'openrouter-status openrouter-status-loading';

		apiFetch<OpenRouterModel[]>({
			path: '/rtcamps-ai-provider-for-openrouter/v1/models',
		})
			.then(function (data) {
				allModels = data;
				isLoaded = true;

				statusEl.textContent =
					allModels.length +
					' ' +
					(i18n.modelsCount ||
						__(
							'models available.',
							'rtcamps-ai-provider-for-openrouter'
						));
				statusEl.className =
					'openrouter-status openrouter-status-ready';
				statusEl.style.color = ''; // reset to stylesheet default

				// Render info badge details for the already active selection
				if (savedModel) {
					const found = allModels.find(
						(model) => model.id === savedModel
					) || { id: savedModel };
					renderModelInfo(found, infoEl);
				}
			})
			.catch(function (error: unknown) {
				const err = error as { message?: string };
				statusEl.textContent =
					err?.message ||
					i18n.errorLoad ||
					__(
						'Could not load models.',
						'rtcamps-ai-provider-for-openrouter'
					);
				statusEl.className = 'openrouter-status';
				statusEl.style.color = '#d63638';
			});
	}

	/**
	 * Fetches image-generation models and resolves pre-loaded pricing cards.
	 *
	 * @param savedImageModel Saved image model identifier value.
	 * @param imageStatusEl   Status element container.
	 * @param imageInfoEl     Information details card container.
	 */
	function loadImageModels(
		savedImageModel: string,
		imageStatusEl: HTMLElement,
		imageInfoEl: HTMLElement
	): void {
		imageStatusEl.textContent =
			i18n.loading ||
			__('Loading models…', 'rtcamps-ai-provider-for-openrouter');
		imageStatusEl.className = 'openrouter-status openrouter-status-loading';

		apiFetch<OpenRouterModel[]>({
			path: '/rtcamps-ai-provider-for-openrouter/v1/image-models',
		})
			.then(function (data) {
				allImageModels = data;
				isImageLoaded = true;

				imageStatusEl.textContent =
					allImageModels.length +
					' ' +
					__(
						'image models available.',
						'rtcamps-ai-provider-for-openrouter'
					);
				imageStatusEl.className =
					'openrouter-status openrouter-status-ready';
				imageStatusEl.style.color = ''; // reset to stylesheet default

				if (savedImageModel) {
					const selectedImageModelData =
						getImageModelMatches('').find(function (model) {
							return model.id === savedImageModel;
						}) ||
						(savedImageModel ? { id: savedImageModel } : null);
					renderModelInfo(selectedImageModelData, imageInfoEl);
				}
			})
			.catch(function (error: unknown) {
				const err = error as { message?: string };
				imageStatusEl.textContent =
					err?.message ||
					i18n.errorLoad ||
					__(
						'Could not load models.',
						'rtcamps-ai-provider-for-openrouter'
					);
				imageStatusEl.className = 'openrouter-status';
				imageStatusEl.style.color = '#d63638';
			});
	}

	/**
	 * Bootstraps autocompletes and layout handles once settings are loaded.
	 */
	function init(): void {
		const searchInput = document.getElementById(
			'ai_provider_for_openrouter_by_rtcamp_settings-model-search'
		) as HTMLInputElement | null;
		const hiddenInput = document.getElementById(
			'ai_provider_for_openrouter_by_rtcamp_settings-model-value'
		) as HTMLInputElement | null;
		const dropdown = document.getElementById('openrouter-model-dropdown');
		const infoEl = document.getElementById('openrouter-model-info');
		const statusEl = document.getElementById('openrouter-model-status');
		const imageSearchInput = document.getElementById(
			'ai_provider_for_openrouter_by_rtcamp_settings-image-model-search'
		) as HTMLInputElement | null;
		const imageHiddenInput = document.getElementById(
			'ai_provider_for_openrouter_by_rtcamp_settings-image-model-value'
		) as HTMLInputElement | null;
		const imageDropdown = document.getElementById(
			'openrouter-image-model-dropdown'
		);
		const imageInfoEl = document.getElementById(
			'openrouter-image-model-info'
		);
		const imageStatusEl = document.getElementById(
			'openrouter-image-model-status'
		);

		// Exit if fields aren't present on the page
		if (
			!searchInput ||
			!hiddenInput ||
			!dropdown ||
			!infoEl ||
			!statusEl ||
			!imageSearchInput ||
			!imageHiddenInput ||
			!imageDropdown ||
			!imageInfoEl ||
			!imageStatusEl
		) {
			return;
		}

		const savedModel = settings.selectedModel || '';
		const savedImageModel = settings.selectedImageModel || '';

		// Trigger model loading
		loadModels(savedModel, statusEl, infoEl);
		loadImageModels(savedImageModel, imageStatusEl, imageInfoEl);

		// Text Model inputs and events
		searchInput.addEventListener('input', function () {
			hiddenInput.value = this.value.trim();

			if (!isLoaded) {
				return;
			}

			const query = this.value.trim().toLowerCase();

			if (query.length < 3) {
				dropdown.style.display = 'none';
				return;
			}

			filterModels(query, dropdown, searchInput, hiddenInput, infoEl);
		});

		searchInput.addEventListener('blur', function () {
			// Delay a split-second to allow dropdown selection clicks to resolve first
			setTimeout(function () {
				dropdown.style.display = 'none';
			}, 200);
		});

		searchInput.addEventListener('focus', function () {
			if (!isLoaded) {
				return;
			}

			const query = this.value.trim().toLowerCase();
			filterModels(query, dropdown, searchInput, hiddenInput, infoEl);
		});

		searchInput.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				dropdown.style.display = 'none';
			}
		});

		// Image Model inputs and events
		imageSearchInput.addEventListener('input', function () {
			imageHiddenInput.value = this.value.trim();

			if (!isImageLoaded) {
				return;
			}

			const query = this.value.trim().toLowerCase();
			filterImageModels(
				query,
				imageDropdown,
				imageSearchInput,
				imageHiddenInput,
				imageInfoEl
			);
		});

		imageSearchInput.addEventListener('focus', function () {
			if (!isImageLoaded) {
				imageStatusEl.textContent =
					i18n.loading ||
					__('Loading models…', 'rtcamps-ai-provider-for-openrouter');
				return;
			}

			imageStatusEl.textContent =
				getImageModelMatches('').length +
				' ' +
				__(
					'image models available.',
					'rtcamps-ai-provider-for-openrouter'
				);
			filterImageModels(
				this.value.trim().toLowerCase(),
				imageDropdown,
				imageSearchInput,
				imageHiddenInput,
				imageInfoEl
			);
		});

		imageSearchInput.addEventListener('blur', function () {
			setTimeout(function () {
				imageDropdown.style.display = 'none';
			}, 200);
		});

		imageSearchInput.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				imageDropdown.style.display = 'none';
			}
		});
	}

	// Initialize logic when page and DOM are ready
	domReady(init);
})();
