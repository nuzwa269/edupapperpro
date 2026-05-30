/**
 * EduPaper AI Chat frontend script.
 *
 * Handles:
 * - Custom disclosure dropdowns
 * - Provider switching
 * - Prompt building
 * - Copy prompt
 * - REST API request to the WordPress backend
 *
 * This version sends data as application/x-www-form-urlencoded and reads
 * both JSON and plain/HTML error bodies, so real server/API errors appear
 * instead of a generic "Something went wrong" message.
 */
(function () {
  'use strict';

  let data = window.EPAC_DATA || {};
  let hasFrontendConfig = !!window.EPAC_DATA;
  const i18n = data.i18n || {};

  const defaults = {
    copied: 'Prompt copied.',
    working: 'Generating paper...',
    error: 'Something went wrong. Please try again.',
    configMissing: 'EduPaper AI Chat frontend config is missing. Clear cache and make sure wp_localize_script is loading before this JS file.',
    endpointMissing: 'EduPaper AI Chat endpoint or nonce is missing. Clear cache, disable JS optimization temporarily, and reload the page.',
    emptyPrompt: 'Please select options or add instructions first.',
    assistantHi: 'Select the paper options above. Your prompt will appear below, then I can generate the paper here.',
  };

  const text = Object.assign({}, defaults, i18n);

  function parseJsonAttribute(value, fallback) {
    if (!value) {
      return fallback;
    }

    try {
      return JSON.parse(value);
    } catch (error) {
      return fallback;
    }
  }

  function hydrateConfigFromRoot(root) {
    if (!root) {
      return;
    }

    const endpoint = root.getAttribute('data-epac-endpoint') || '';
    const nonce = root.getAttribute('data-epac-nonce') || '';
    const defaultProvider = root.getAttribute('data-epac-default-provider') || '';
    const showProviderSwitch = root.getAttribute('data-epac-show-provider-switch');
    const enabledProviders = parseJsonAttribute(
      root.getAttribute('data-epac-enabled-providers'),
      []
    );

    data = Object.assign(
      {},
      data,
      {
        endpoint: data.endpoint || endpoint,
        nonce: data.nonce || nonce,
        defaultProvider: data.defaultProvider || defaultProvider,
        showProviderSwitch:
          typeof data.showProviderSwitch === 'boolean'
            ? data.showProviderSwitch
            : showProviderSwitch === '1',
        enabledProviders:
          Array.isArray(data.enabledProviders) && data.enabledProviders.length
            ? data.enabledProviders
            : enabledProviders,
      }
    );

    hasFrontendConfig = !!(data.endpoint && data.nonce);

    if (!window.EPAC_DATA && hasFrontendConfig) {
      window.EPAC_DATA = data;
    }
  }

  function qs(root, selector) {
    return root.querySelector(selector);
  }

  function qsa(root, selector) {
    return Array.prototype.slice.call(root.querySelectorAll(selector));
  }

  function normalize(value) {
    return String(value || '').trim();
  }

  function stripHtml(value) {
    const div = document.createElement('div');
    div.innerHTML = String(value || '');
    return normalize(div.textContent || div.innerText || '');
  }

  function isBlankChoice(value) {
    const clean = normalize(value).toLowerCase();

    return (
      clean === '' ||
      clean === 'not specified' ||
      clean === '— select —' ||
      clean === 'select' ||
      clean === 'none' ||
      clean === 'no'
    );
  }

  function setNotice(root, message, type) {
    const notice = qs(root, '[data-epac-notice]');

    if (!notice) {
      return;
    }

    notice.textContent = message || '';
    notice.dataset.epacNoticeType = type || 'info';
  }

  function setBusy(root, busy) {
    const sendButton = qs(root, '[data-epac-send]');
    const copyButton = qs(root, '[data-epac-copy]');

    if (sendButton) {
      sendButton.disabled = !!busy;
      sendButton.setAttribute('aria-busy', busy ? 'true' : 'false');

      if (busy) {
        if (!sendButton.dataset.epacOriginalText) {
          sendButton.dataset.epacOriginalText = sendButton.textContent;
        }

        sendButton.textContent = text.working;
      } else if (sendButton.dataset.epacOriginalText) {
        sendButton.textContent = sendButton.dataset.epacOriginalText;
        delete sendButton.dataset.epacOriginalText;
      }
    }

    if (copyButton) {
      copyButton.disabled = !!busy;
    }
  }

  function closeDropdown(dropdown) {
    if (!dropdown) {
      return;
    }

    dropdown.classList.remove('epac-dropdown--open');

    const toggle = qs(dropdown, '.epac-dropdown__toggle');

    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
  }

  function closeAllDropdowns(root, exceptDropdown) {
    qsa(root, '[data-epac-dropdown]').forEach(function (dropdown) {
      if (dropdown !== exceptDropdown) {
        closeDropdown(dropdown);
      }
    });
  }

  function openDropdown(root, dropdown) {
    if (!dropdown) {
      return;
    }

    closeAllDropdowns(root, dropdown);
    dropdown.classList.add('epac-dropdown--open');

    const toggle = qs(dropdown, '.epac-dropdown__toggle');

    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
  }

  function toggleDropdown(root, dropdown) {
    if (!dropdown) {
      return;
    }

    if (dropdown.classList.contains('epac-dropdown--open')) {
      closeDropdown(dropdown);
    } else {
      openDropdown(root, dropdown);
    }
  }

  function selectDropdownOption(root, dropdown, optionButton) {
    if (!dropdown || !optionButton) {
      return;
    }

    const value = normalize(
      optionButton.getAttribute('data-epac-option') || optionButton.textContent
    );

    dropdown.dataset.epacValue = value;

    const current = qs(dropdown, '[data-epac-current]');

    if (current) {
      current.textContent = value;
    }

    qsa(dropdown, '.epac-dropdown__option').forEach(function (button) {
      button.setAttribute('aria-selected', button === optionButton ? 'true' : 'false');
    });

    closeDropdown(dropdown);
    updatePrompt(root);

    const toggle = qs(dropdown, '.epac-dropdown__toggle');

    if (toggle) {
      toggle.focus({ preventScroll: true });
    }
  }

  function focusOption(dropdown, direction) {
    const options = qsa(dropdown, '.epac-dropdown__option');

    if (!options.length) {
      return;
    }

    const currentIndex = options.indexOf(document.activeElement);
    let nextIndex = 0;

    if (direction === 'first') {
      nextIndex = 0;
    } else if (direction === 'last') {
      nextIndex = options.length - 1;
    } else if (direction === 'next') {
      nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % options.length;
    } else if (direction === 'previous') {
      nextIndex =
        currentIndex < 0
          ? options.length - 1
          : (currentIndex - 1 + options.length) % options.length;
    }

    options[nextIndex].focus({ preventScroll: true });
  }

  function initDropdowns(root) {
    qsa(root, '[data-epac-dropdown]').forEach(function (dropdown) {
      const toggle = qs(dropdown, '.epac-dropdown__toggle');
      const options = qsa(dropdown, '.epac-dropdown__option');

      if (toggle) {
        toggle.addEventListener('click', function () {
          toggleDropdown(root, dropdown);
        });

        toggle.addEventListener('keydown', function (event) {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            openDropdown(root, dropdown);
            focusOption(dropdown, 'next');
          }

          if (event.key === 'ArrowUp') {
            event.preventDefault();
            openDropdown(root, dropdown);
            focusOption(dropdown, 'last');
          }

          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleDropdown(root, dropdown);
          }

          if (event.key === 'Escape') {
            event.preventDefault();
            closeDropdown(dropdown);
          }
        });
      }

      options.forEach(function (optionButton) {
        optionButton.addEventListener('click', function () {
          selectDropdownOption(root, dropdown, optionButton);
        });

        optionButton.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectDropdownOption(root, dropdown, optionButton);
          }

          if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusOption(dropdown, 'next');
          }

          if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusOption(dropdown, 'previous');
          }

          if (event.key === 'Home') {
            event.preventDefault();
            focusOption(dropdown, 'first');
          }

          if (event.key === 'End') {
            event.preventDefault();
            focusOption(dropdown, 'last');
          }

          if (event.key === 'Escape') {
            event.preventDefault();
            closeDropdown(dropdown);

            if (toggle) {
              toggle.focus({ preventScroll: true });
            }
          }
        });
      });
    });

    document.addEventListener('click', function (event) {
      if (!root.contains(event.target)) {
        closeAllDropdowns(root, null);
        return;
      }

      const clickedDropdown = event.target.closest('[data-epac-dropdown]');

      if (!clickedDropdown || !root.contains(clickedDropdown)) {
        closeAllDropdowns(root, null);
      }
    });
  }

  function getSelections(root) {
    const selections = {};

    qsa(root, '[data-epac-dropdown]').forEach(function (dropdown) {
      const field = normalize(dropdown.getAttribute('data-epac-field'));
      const label = normalize(dropdown.getAttribute('data-epac-label'));
      const value = normalize(dropdown.getAttribute('data-epac-value'));

      if (field) {
        selections[field] = {
          label: label || field,
          value: value,
        };
      }
    });

    return selections;
  }

  function getSelectionValue(selections, key) {
    return selections[key] ? normalize(selections[key].value) : '';
  }

  function buildPrompt(root) {
    const selections = getSelections(root);
    const extraInput = qs(root, '[data-epac-extra]');
    const extra = extraInput ? normalize(extraInput.value) : '';

    const grade = getSelectionValue(selections, 'grade');
    const subject = getSelectionValue(selections, 'subject');
    const board = getSelectionValue(selections, 'board');
    const testType = getSelectionValue(selections, 'testType');
    const questionType = getSelectionValue(selections, 'questionType');
    const duration = getSelectionValue(selections, 'duration');
    const language = getSelectionValue(selections, 'language');
    const bloom = getSelectionValue(selections, 'bloom');
    const bloomLevel = getSelectionValue(selections, 'bloomLevel');
    const mcqs = getSelectionValue(selections, 'mcqs');
    const difficulty = getSelectionValue(selections, 'difficulty');

    let prompt = '';

    if (
      (testType && !isBlankChoice(testType)) ||
      (grade && !isBlankChoice(grade)) ||
      (subject && !isBlankChoice(subject)) ||
      (board && !isBlankChoice(board))
    ) {
      prompt += 'I need';

      if (testType && !isBlankChoice(testType)) {
        prompt += ' a ' + testType + ' paper';
      } else {
        prompt += ' an exam paper';
      }

      if (
        (grade && !isBlankChoice(grade)) ||
        (subject && !isBlankChoice(subject))
      ) {
        prompt += ' for';

        if (grade && !isBlankChoice(grade)) {
          prompt += ' ' + grade;
        }

        if (subject && !isBlankChoice(subject)) {
          prompt += grade && !isBlankChoice(grade) ? ' - ' + subject : ' ' + subject;
        }
      }

      if (board && !isBlankChoice(board)) {
        prompt += ', Board: ' + board;
      }

      prompt += '.';
    }

    if (questionType && !isBlankChoice(questionType)) {
      prompt += ' Question Type: ' + questionType + '.';
    }

    if (duration && !isBlankChoice(duration)) {
      prompt += ' Duration: ' + duration + '.';
    }

    if (mcqs && !isBlankChoice(mcqs)) {
      prompt += ' MCQs: ' + mcqs + '.';
    }

    if (language && !isBlankChoice(language)) {
      prompt += ' Language: ' + language + '.';
    }

    if (bloom && !isBlankChoice(bloom)) {
      prompt += ' Bloom Taxonomy: ' + bloom + '.';
    }

    if (bloomLevel && !isBlankChoice(bloomLevel)) {
      prompt += ' Bloom Level Emphasis: ' + bloomLevel + '.';
    }

    if (difficulty && !isBlankChoice(difficulty)) {
      prompt += ' Difficulty: ' + difficulty + '.';
    }

    if (extra) {
      prompt += ' Additional instructions: ' + extra + '.';
    }

    return normalize(prompt);
  }

  function updatePrompt(root) {
    const promptBox = qs(root, '[data-epac-prompt]');

    if (!promptBox) {
      return '';
    }

    const prompt = buildPrompt(root);
    promptBox.value = prompt;

    return prompt;
  }

  function getSelectedProvider(root) {
    const checked = qs(root, '[data-epac-provider][aria-checked="true"]');

    if (checked) {
      return normalize(checked.getAttribute('data-epac-provider'));
    }

    if (data.defaultProvider) {
      return normalize(data.defaultProvider);
    }

    const enabledProviders = Array.isArray(data.enabledProviders)
      ? data.enabledProviders
      : [];

    if (enabledProviders.length && enabledProviders[0].value) {
      return normalize(enabledProviders[0].value);
    }

    return 'xai';
  }

  function initProviderPicker(root) {
    const buttons = qsa(root, '[data-epac-provider]');

    if (!buttons.length) {
      return;
    }

    let hasChecked = false;

    buttons.forEach(function (button) {
      if (button.getAttribute('aria-checked') === 'true') {
        hasChecked = true;
      }
    });

    if (!hasChecked) {
      const defaultButton =
        buttons.find(function (button) {
          return button.getAttribute('data-epac-provider') === data.defaultProvider;
        }) || buttons[0];

      if (defaultButton) {
        defaultButton.setAttribute('aria-checked', 'true');
      }
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        buttons.forEach(function (otherButton) {
          otherButton.setAttribute(
            'aria-checked',
            otherButton === button ? 'true' : 'false'
          );
        });

        setNotice(root, '', 'info');
      });

      button.addEventListener('keydown', function (event) {
        const currentIndex = buttons.indexOf(button);
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
          event.preventDefault();
          nextIndex = (currentIndex + 1) % buttons.length;
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
          event.preventDefault();
          nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
        }

        if (event.key === 'Home') {
          event.preventDefault();
          nextIndex = 0;
        }

        if (event.key === 'End') {
          event.preventDefault();
          nextIndex = buttons.length - 1;
        }

        if (nextIndex !== currentIndex) {
          buttons[nextIndex].focus({ preventScroll: true });
          buttons[nextIndex].click();
        }
      });
    });
  }

  function appendMessage(root, role, content, provider) {
    const messages = qs(root, '[data-epac-messages]');

    if (!messages) {
      return;
    }

    const message = document.createElement('div');
    message.className =
      'epac-message epac-message--' + (role === 'user' ? 'user' : 'assistant');

    const avatar = document.createElement('div');
    avatar.className = 'epac-message__avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = role === 'user' ? 'You' : 'AI';

    const bubble = document.createElement('div');
    bubble.className = 'epac-message__bubble';

    if (provider && role !== 'user') {
      const meta = document.createElement('div');
      meta.className = 'epac-message__meta';
      meta.textContent = 'Provider: ' + provider;
      bubble.appendChild(meta);
    }

    const body = document.createElement('div');
    body.className = 'epac-message__text';
    body.textContent = content || '';
    bubble.appendChild(body);

    message.appendChild(avatar);
    message.appendChild(bubble);
    messages.appendChild(message);

    messages.scrollTop = messages.scrollHeight;
  }

  function parseErrorMessage(errorPayload) {
    if (!errorPayload) {
      return text.error;
    }

    if (typeof errorPayload === 'string') {
      return normalize(errorPayload) || text.error;
    }

    if (errorPayload.message) {
      const code = errorPayload.code ? ' [' + String(errorPayload.code) + ']' : '';
      const status = errorPayload.httpStatus ? ' HTTP ' + String(errorPayload.httpStatus) + ':' : '';
      return normalize(status + code + ' ' + String(errorPayload.message));
    }

    if (errorPayload.data && errorPayload.data.message) {
      return String(errorPayload.data.message);
    }

    if (errorPayload.error && errorPayload.error.message) {
      return String(errorPayload.error.message);
    }

    return text.error;
  }

  function parseFetchResponse(response) {
    return response.text().then(function (rawText) {
      let payload = null;
      const cleanText = normalize(rawText);

      if (cleanText) {
        try {
          payload = JSON.parse(cleanText);
        } catch (error) {
          payload = null;
        }
      }

      if (!response.ok) {
        if (payload) {
          if (typeof payload === 'object' && payload && !payload.httpStatus) {
            payload.httpStatus = response.status;
          }
          throw payload;
        }

        const snippet = stripHtml(cleanText).slice(0, 260);
        throw {
          message:
            snippet ||
            'Request failed with HTTP ' + response.status + ' ' + response.statusText,
        };
      }

      if (payload) {
        return payload;
      }

      throw {
        message: cleanText
          ? 'Server returned a non-JSON response: ' + stripHtml(cleanText).slice(0, 260)
          : text.error,
      };
    });
  }

  function sendPrompt(root) {
    const prompt = updatePrompt(root);
    const provider = getSelectedProvider(root);

    if (!prompt) {
      setNotice(root, text.emptyPrompt, 'error');
      return;
    }

    if (!hasFrontendConfig) {
      const message = text.configMissing;
      appendMessage(root, 'assistant', message);
      setNotice(root, message, 'error');
      return;
    }

    if (!data.endpoint || !data.nonce) {
      const message = text.endpointMissing + ' endpoint=' + (data.endpoint ? 'yes' : 'no') + ', nonce=' + (data.nonce ? 'yes' : 'no') + '.';
      appendMessage(root, 'assistant', message);
      setNotice(root, message, 'error');
      return;
    }

    appendMessage(root, 'user', prompt);
    setNotice(root, text.working, 'info');
    setBusy(root, true);

    const body = new URLSearchParams();
    body.set('prompt', prompt);
    body.set('provider', provider);

    window
      .fetch(data.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          Accept: 'application/json',
          'X-WP-Nonce': data.nonce,
          'X-EPAC-Nonce': data.nonce,
        },
        body: body.toString(),
      })
      .then(parseFetchResponse)
      .then(function (payload) {
        const answer = normalize(payload.answer);
        const responseProvider = normalize(payload.provider || provider);

        if (!answer) {
          throw { message: text.error };
        }

        appendMessage(root, 'assistant', answer, responseProvider);
        setNotice(root, '', 'success');
      })
      .catch(function (errorPayload) {
        const message = parseErrorMessage(errorPayload);

        appendMessage(root, 'assistant', message);
        setNotice(root, message, 'error');
      })
      .finally(function () {
        setBusy(root, false);
      });
  }

  function fallbackCopy(root, promptBox, prompt) {
    if (!promptBox) {
      setNotice(root, text.error, 'error');
      return;
    }

    const previousReadonly = promptBox.hasAttribute('readonly');

    promptBox.removeAttribute('readonly');
    promptBox.value = prompt;
    promptBox.focus();
    promptBox.select();

    try {
      const copied = document.execCommand('copy');

      setNotice(
        root,
        copied ? text.copied : text.error,
        copied ? 'success' : 'error'
      );
    } catch (error) {
      setNotice(root, text.error, 'error');
    }

    if (previousReadonly) {
      promptBox.setAttribute('readonly', 'readonly');
    }
  }

  function copyPrompt(root) {
    const prompt = updatePrompt(root);
    const promptBox = qs(root, '[data-epac-prompt]');

    if (!prompt) {
      setNotice(root, text.emptyPrompt, 'error');
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard
        .writeText(prompt)
        .then(function () {
          setNotice(root, text.copied, 'success');
        })
        .catch(function () {
          fallbackCopy(root, promptBox, prompt);
        });

      return;
    }

    fallbackCopy(root, promptBox, prompt);
  }

  function bindComposer(root) {
    const extraInput = qs(root, '[data-epac-extra]');
    const copyButton = qs(root, '[data-epac-copy]');
    const sendButton = qs(root, '[data-epac-send]');

    if (extraInput) {
      extraInput.addEventListener('input', function () {
        updatePrompt(root);
      });
    }

    if (copyButton) {
      copyButton.addEventListener('click', function () {
        copyPrompt(root);
      });
    }

    if (sendButton) {
      sendButton.addEventListener('click', function () {
        sendPrompt(root);
      });
    }
  }

  function initRoot(root) {
    if (root.dataset.epacInitialized === '1') {
      return;
    }

    root.dataset.epacInitialized = '1';
    hydrateConfigFromRoot(root);

    initDropdowns(root);
    initProviderPicker(root);
    bindComposer(root);
    updatePrompt(root);
  }

  function init() {
    qsa(document, '[data-epac-root]').forEach(function (root) {
      initRoot(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
