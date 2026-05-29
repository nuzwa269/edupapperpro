<?php
/**
 * Plugin Name: EduPaper AI Chat
 * Plugin URI: https://github.com/ummeawais19/edupaper-ai-chat
 * Description: A full-width, mobile-responsive exam paper prompt builder with provider support for OpenAI/ChatGPT, Google Gemini, xAI/Grok, and custom OpenAI-compatible APIs.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nazhat Wasim
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: edupaper-ai-chat
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPAC_VERSION', '2.0.0' );
define( 'EPAC_PLUGIN_FILE', __FILE__ );
define( 'EPAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPAC_OPTION_NAME', 'epac_settings' );
define( 'EPAC_REST_NAMESPACE', 'edupaper-ai-chat/v1' );
define( 'EPAC_TEXT_DOMAIN', 'edupaper-ai-chat' );

/**
 * Load plugin text domain.
 *
 * @return void
 */
function epac_load_textdomain() {
	load_plugin_textdomain( 'edupaper-ai-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'epac_load_textdomain' );

/**
 * Default plugin settings.
 *
 * @return array<string, mixed>
 */
function epac_default_settings() {
	return array(
		'default_provider'      => 'xai',
		'show_provider_switch'  => '1',
		'external_consent'      => '0',
		'temperature'           => '0.3',
		'max_output_tokens'     => '1500',
		'rate_limit_per_10_min' => '20',
		'system_prompt'         => 'You are EduPaper AI Chat, an expert educational assessment designer. Create exam papers that are curriculum-aware, age-appropriate, clear, well formatted, and useful for teachers. When relevant, include answer keys, marking schemes, Bloom taxonomy tags, and marks distribution.',

		'openai_enabled'        => '0',
		'openai_api_key'        => '',
		'openai_model'          => 'gpt-4.1',
		'openai_endpoint'       => 'https://api.openai.com/v1/responses',

		'gemini_enabled'        => '0',
		'gemini_api_key'        => '',
		'gemini_model'          => 'gemini-3.5-flash',
		'gemini_endpoint_base'  => 'https://generativelanguage.googleapis.com/v1beta',

		'xai_enabled'           => '1',
		'xai_api_key'           => '',
		'xai_model'             => 'grok-4.3',
		'xai_endpoint'          => 'https://api.x.ai/v1/chat/completions',

		'custom_enabled'        => '0',
		'custom_provider_name'  => 'Custom API',
		'custom_api_key'        => '',
		'custom_model'          => '',
		'custom_endpoint'       => '',
		'custom_auth_prefix'    => 'Bearer',
	);
}

/**
 * Get provider labels.
 *
 * @return array<string, string>
 */
function epac_provider_labels() {
	return array(
		'openai' => __( 'ChatGPT / OpenAI', 'edupaper-ai-chat' ),
		'gemini' => __( 'Google Gemini', 'edupaper-ai-chat' ),
		'xai'    => __( 'Grok / xAI', 'edupaper-ai-chat' ),
		'custom' => __( 'Custom API', 'edupaper-ai-chat' ),
	);
}

/**
 * Get merged settings.
 *
 * @return array<string, mixed>
 */
function epac_get_settings() {
	$saved = get_option( EPAC_OPTION_NAME, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, epac_default_settings() );
}

/**
 * Sanitize a checkbox-style field.
 *
 * @param array<string, mixed> $input Raw settings.
 * @param string               $key Setting key.
 * @return string
 */
function epac_sanitize_checkbox( $input, $key ) {
	return ! empty( $input[ $key ] ) ? '1' : '0';
}

/**
 * Sanitize numeric text field with min/max bounds.
 *
 * @param mixed $value Raw value.
 * @param float $default Default value.
 * @param float $min Minimum.
 * @param float $max Maximum.
 * @return string
 */
function epac_sanitize_float_string( $value, $default, $min, $max ) {
	$value = is_numeric( $value ) ? (float) $value : (float) $default;
	$value = max( $min, min( $max, $value ) );
	return (string) $value;
}

/**
 * Sanitize integer text field with min/max bounds.
 *
 * @param mixed $value Raw value.
 * @param int   $default Default value.
 * @param int   $min Minimum.
 * @param int   $max Maximum.
 * @return string
 */
function epac_sanitize_int_string( $value, $default, $min, $max ) {
	$value = is_numeric( $value ) ? absint( $value ) : absint( $default );
	$value = max( $min, min( $max, $value ) );
	return (string) $value;
}

/**
 * Preserve saved API key when the password field is left blank; clear it when requested.
 *
 * @param array<string, mixed> $input Raw settings.
 * @param array<string, mixed> $previous Existing saved settings.
 * @param string               $key API key field.
 * @return string
 */
function epac_sanitize_api_key_field( $input, $previous, $key ) {
	$clear_key = 'clear_' . $key;
	if ( ! empty( $input[ $clear_key ] ) ) {
		return '';
	}

	if ( isset( $input[ $key ] ) ) {
		$new_value = sanitize_text_field( wp_unslash( $input[ $key ] ) );
		if ( '' !== $new_value ) {
			return $new_value;
		}
	}

	return isset( $previous[ $key ] ) ? (string) $previous[ $key ] : '';
}

/**
 * Sanitize plugin settings before saving.
 *
 * @param array<string, mixed> $input Raw input.
 * @return array<string, mixed>
 */
function epac_sanitize_settings( $input ) {
	$defaults = epac_default_settings();
	$previous = epac_get_settings();
	$input    = is_array( $input ) ? $input : array();
	$clean    = array();

	$providers = array_keys( epac_provider_labels() );
	$provider  = isset( $input['default_provider'] ) ? sanitize_key( wp_unslash( $input['default_provider'] ) ) : $defaults['default_provider'];
	if ( ! in_array( $provider, $providers, true ) ) {
		$provider = $defaults['default_provider'];
	}
	$clean['default_provider'] = $provider;

	$clean['show_provider_switch']  = epac_sanitize_checkbox( $input, 'show_provider_switch' );
	$clean['external_consent']      = epac_sanitize_checkbox( $input, 'external_consent' );
	$clean['temperature']           = epac_sanitize_float_string( isset( $input['temperature'] ) ? $input['temperature'] : $defaults['temperature'], $defaults['temperature'], 0, 2 );
	$clean['max_output_tokens']     = epac_sanitize_int_string( isset( $input['max_output_tokens'] ) ? $input['max_output_tokens'] : $defaults['max_output_tokens'], (int) $defaults['max_output_tokens'], 100, 12000 );
	$clean['rate_limit_per_10_min'] = epac_sanitize_int_string( isset( $input['rate_limit_per_10_min'] ) ? $input['rate_limit_per_10_min'] : $defaults['rate_limit_per_10_min'], (int) $defaults['rate_limit_per_10_min'], 1, 200 );
	$clean['system_prompt']         = isset( $input['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['system_prompt'] ) ) : $defaults['system_prompt'];

	$clean['openai_enabled']  = epac_sanitize_checkbox( $input, 'openai_enabled' );
	$clean['openai_api_key']  = epac_sanitize_api_key_field( $input, $previous, 'openai_api_key' );
	$clean['openai_model']    = isset( $input['openai_model'] ) ? sanitize_text_field( wp_unslash( $input['openai_model'] ) ) : $defaults['openai_model'];
	$clean['openai_endpoint'] = isset( $input['openai_endpoint'] ) ? esc_url_raw( wp_unslash( $input['openai_endpoint'] ) ) : $defaults['openai_endpoint'];
	if ( '' === $clean['openai_endpoint'] ) {
		$clean['openai_endpoint'] = $defaults['openai_endpoint'];
	}

	$clean['gemini_enabled']       = epac_sanitize_checkbox( $input, 'gemini_enabled' );
	$clean['gemini_api_key']       = epac_sanitize_api_key_field( $input, $previous, 'gemini_api_key' );
	$clean['gemini_model']         = isset( $input['gemini_model'] ) ? sanitize_text_field( wp_unslash( $input['gemini_model'] ) ) : $defaults['gemini_model'];
	$clean['gemini_endpoint_base'] = isset( $input['gemini_endpoint_base'] ) ? esc_url_raw( wp_unslash( $input['gemini_endpoint_base'] ) ) : $defaults['gemini_endpoint_base'];
	if ( '' === $clean['gemini_endpoint_base'] ) {
		$clean['gemini_endpoint_base'] = $defaults['gemini_endpoint_base'];
	}

	$clean['xai_enabled']  = epac_sanitize_checkbox( $input, 'xai_enabled' );
	$clean['xai_api_key']  = epac_sanitize_api_key_field( $input, $previous, 'xai_api_key' );
	$clean['xai_model']    = isset( $input['xai_model'] ) ? sanitize_text_field( wp_unslash( $input['xai_model'] ) ) : $defaults['xai_model'];
	$clean['xai_endpoint'] = isset( $input['xai_endpoint'] ) ? esc_url_raw( wp_unslash( $input['xai_endpoint'] ) ) : $defaults['xai_endpoint'];
	if ( '' === $clean['xai_endpoint'] ) {
		$clean['xai_endpoint'] = $defaults['xai_endpoint'];
	}

	$clean['custom_enabled']       = epac_sanitize_checkbox( $input, 'custom_enabled' );
	$clean['custom_provider_name'] = isset( $input['custom_provider_name'] ) ? sanitize_text_field( wp_unslash( $input['custom_provider_name'] ) ) : $defaults['custom_provider_name'];
	$clean['custom_api_key']       = epac_sanitize_api_key_field( $input, $previous, 'custom_api_key' );
	$clean['custom_model']         = isset( $input['custom_model'] ) ? sanitize_text_field( wp_unslash( $input['custom_model'] ) ) : $defaults['custom_model'];
	$clean['custom_endpoint']      = isset( $input['custom_endpoint'] ) ? esc_url_raw( wp_unslash( $input['custom_endpoint'] ) ) : $defaults['custom_endpoint'];
	$clean['custom_auth_prefix']   = isset( $input['custom_auth_prefix'] ) ? sanitize_text_field( wp_unslash( $input['custom_auth_prefix'] ) ) : $defaults['custom_auth_prefix'];
	$clean['custom_auth_prefix']   = '' === $clean['custom_auth_prefix'] ? $defaults['custom_auth_prefix'] : $clean['custom_auth_prefix'];

	return $clean;
}

/**
 * Register settings.
 *
 * @return void
 */
function epac_register_settings() {
	register_setting(
		'epac_settings_group',
		EPAC_OPTION_NAME,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'epac_sanitize_settings',
			'default'           => epac_default_settings(),
		)
	);
}
add_action( 'admin_init', 'epac_register_settings' );

/**
 * Add settings page under Settings.
 *
 * @return void
 */
function epac_add_settings_page() {
	add_options_page(
		__( 'EduPaper AI Chat', 'edupaper-ai-chat' ),
		__( 'EduPaper AI Chat', 'edupaper-ai-chat' ),
		'manage_options',
		'edupaper-ai-chat',
		'epac_render_settings_page'
	);
}
add_action( 'admin_menu', 'epac_add_settings_page' );

/**
 * Render a password API key field without exposing the saved value.
 *
 * @param string               $field_key Settings field key.
 * @param array<string, mixed> $settings Current settings.
 * @return void
 */
function epac_render_api_key_field( $field_key, $settings ) {
	$field_id = 'epac_' . sanitize_html_class( $field_key );
	$has_key  = ! empty( $settings[ $field_key ] );
	?>
	<input id="<?php echo esc_attr( $field_id ); ?>" class="regular-text" type="password" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_key ? __( 'Saved — leave blank to keep', 'edupaper-ai-chat' ) : __( 'Paste API key', 'edupaper-ai-chat' ) ); ?>" />
	<?php if ( $has_key ) : ?>
		<label style="display:block;margin-top:8px;">
			<input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[clear_<?php echo esc_attr( $field_key ); ?>]" value="1" />
			<?php echo esc_html__( 'Clear saved key', 'edupaper-ai-chat' ); ?>
		</label>
	<?php endif; ?>
	<?php
}

/**
 * Render settings page.
 *
 * @return void
 */
function epac_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = epac_get_settings();
	$labels   = epac_provider_labels();
	?>
	<div class="wrap epac-admin-wrap">
		<h1><?php echo esc_html__( 'EduPaper AI Chat Settings', 'edupaper-ai-chat' ); ?></h1>
		<p><?php echo esc_html__( 'Add one or more AI providers. API keys are stored server-side in WordPress options and are never printed in the frontend HTML or JavaScript.', 'edupaper-ai-chat' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'epac_settings_group' ); ?>

			<h2><?php echo esc_html__( 'General Settings', 'edupaper-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="epac_default_provider"><?php echo esc_html__( 'Default AI Provider', 'edupaper-ai-chat' ); ?></label></th>
					<td>
						<select id="epac_default_provider" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[default_provider]">
							<?php foreach ( $labels as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['default_provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'This provider will be used unless the frontend provider switch is enabled and the visitor selects another provider.', 'edupaper-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Frontend Provider Switch', 'edupaper-ai-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[show_provider_switch]" value="1" <?php checked( '1', $settings['show_provider_switch'] ); ?> />
							<?php echo esc_html__( 'Allow visitors to choose from enabled providers.', 'edupaper-ai-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'External API Consent', 'edupaper-ai-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[external_consent]" value="1" <?php checked( '1', $settings['external_consent'] ); ?> />
							<?php echo esc_html__( 'I understand that visitor prompts will be sent to the selected external AI provider.', 'edupaper-ai-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="epac_temperature"><?php echo esc_html__( 'Temperature', 'edupaper-ai-chat' ); ?></label></th>
					<td><input id="epac_temperature" type="number" step="0.1" min="0" max="2" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[temperature]" value="<?php echo esc_attr( $settings['temperature'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="epac_max_output_tokens"><?php echo esc_html__( 'Max Output Tokens', 'edupaper-ai-chat' ); ?></label></th>
					<td><input id="epac_max_output_tokens" type="number" min="100" max="12000" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[max_output_tokens]" value="<?php echo esc_attr( $settings['max_output_tokens'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="epac_rate_limit"><?php echo esc_html__( 'Rate Limit', 'edupaper-ai-chat' ); ?></label></th>
					<td>
						<input id="epac_rate_limit" type="number" min="1" max="200" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[rate_limit_per_10_min]" value="<?php echo esc_attr( $settings['rate_limit_per_10_min'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Maximum generate requests per visitor IP per 10 minutes.', 'edupaper-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="epac_system_prompt"><?php echo esc_html__( 'System Prompt', 'edupaper-ai-chat' ); ?></label></th>
					<td><textarea id="epac_system_prompt" class="large-text" rows="5" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[system_prompt]"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea></td>
				</tr>
			</table>

			<h2><?php echo esc_html__( 'ChatGPT / OpenAI', 'edupaper-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php echo esc_html__( 'Enable', 'edupaper-ai-chat' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[openai_enabled]" value="1" <?php checked( '1', $settings['openai_enabled'] ); ?> /> <?php echo esc_html__( 'Enable OpenAI provider', 'edupaper-ai-chat' ); ?></label></td></tr>
				<tr><th scope="row"><label for="epac_openai_api_key"><?php echo esc_html__( 'OpenAI API Key', 'edupaper-ai-chat' ); ?></label></th><td><?php epac_render_api_key_field( 'openai_api_key', $settings ); ?></td></tr>
				<tr><th scope="row"><label for="epac_openai_model"><?php echo esc_html__( 'OpenAI Model', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_openai_model" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[openai_model]" value="<?php echo esc_attr( $settings['openai_model'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="epac_openai_endpoint"><?php echo esc_html__( 'OpenAI Endpoint', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_openai_endpoint" class="large-text" type="url" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[openai_endpoint]" value="<?php echo esc_attr( $settings['openai_endpoint'] ); ?>" /><p class="description"><?php echo esc_html__( 'Default uses the OpenAI Responses API.', 'edupaper-ai-chat' ); ?></p></td></tr>
			</table>

			<h2><?php echo esc_html__( 'Google Gemini', 'edupaper-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php echo esc_html__( 'Enable', 'edupaper-ai-chat' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[gemini_enabled]" value="1" <?php checked( '1', $settings['gemini_enabled'] ); ?> /> <?php echo esc_html__( 'Enable Gemini provider', 'edupaper-ai-chat' ); ?></label></td></tr>
				<tr><th scope="row"><label for="epac_gemini_api_key"><?php echo esc_html__( 'Gemini API Key', 'edupaper-ai-chat' ); ?></label></th><td><?php epac_render_api_key_field( 'gemini_api_key', $settings ); ?></td></tr>
				<tr><th scope="row"><label for="epac_gemini_model"><?php echo esc_html__( 'Gemini Model', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_gemini_model" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[gemini_model]" value="<?php echo esc_attr( $settings['gemini_model'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="epac_gemini_endpoint_base"><?php echo esc_html__( 'Gemini Endpoint Base', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_gemini_endpoint_base" class="large-text" type="url" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[gemini_endpoint_base]" value="<?php echo esc_attr( $settings['gemini_endpoint_base'] ); ?>" /><p class="description"><?php echo esc_html__( 'The plugin appends /models/{model}:generateContent?key=YOUR_KEY.', 'edupaper-ai-chat' ); ?></p></td></tr>
			</table>

			<h2><?php echo esc_html__( 'Grok / xAI', 'edupaper-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php echo esc_html__( 'Enable', 'edupaper-ai-chat' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[xai_enabled]" value="1" <?php checked( '1', $settings['xai_enabled'] ); ?> /> <?php echo esc_html__( 'Enable xAI/Grok provider', 'edupaper-ai-chat' ); ?></label></td></tr>
				<tr><th scope="row"><label for="epac_xai_api_key"><?php echo esc_html__( 'xAI API Key', 'edupaper-ai-chat' ); ?></label></th><td><?php epac_render_api_key_field( 'xai_api_key', $settings ); ?></td></tr>
				<tr><th scope="row"><label for="epac_xai_model"><?php echo esc_html__( 'xAI Model', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_xai_model" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[xai_model]" value="<?php echo esc_attr( $settings['xai_model'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="epac_xai_endpoint"><?php echo esc_html__( 'xAI Endpoint', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_xai_endpoint" class="large-text" type="url" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[xai_endpoint]" value="<?php echo esc_attr( $settings['xai_endpoint'] ); ?>" /></td></tr>
			</table>

			<h2><?php echo esc_html__( 'Custom OpenAI-Compatible API', 'edupaper-ai-chat' ); ?></h2>
			<p><?php echo esc_html__( 'This is for APIs that accept Chat Completions-style JSON: model + messages, and return choices[0].message.content. Truly different APIs need a separate adapter.', 'edupaper-ai-chat' ); ?></p>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php echo esc_html__( 'Enable', 'edupaper-ai-chat' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[custom_enabled]" value="1" <?php checked( '1', $settings['custom_enabled'] ); ?> /> <?php echo esc_html__( 'Enable custom provider', 'edupaper-ai-chat' ); ?></label></td></tr>
				<tr><th scope="row"><label for="epac_custom_provider_name"><?php echo esc_html__( 'Provider Name', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_custom_provider_name" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[custom_provider_name]" value="<?php echo esc_attr( $settings['custom_provider_name'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="epac_custom_api_key"><?php echo esc_html__( 'Custom API Key', 'edupaper-ai-chat' ); ?></label></th><td><?php epac_render_api_key_field( 'custom_api_key', $settings ); ?></td></tr>
				<tr><th scope="row"><label for="epac_custom_model"><?php echo esc_html__( 'Custom Model', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_custom_model" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[custom_model]" value="<?php echo esc_attr( $settings['custom_model'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="epac_custom_endpoint"><?php echo esc_html__( 'Custom Endpoint', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_custom_endpoint" class="large-text" type="url" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[custom_endpoint]" value="<?php echo esc_attr( $settings['custom_endpoint'] ); ?>" placeholder="https://example.com/v1/chat/completions" /><p class="description"><?php echo esc_html__( 'Only HTTPS public endpoints are allowed for security.', 'edupaper-ai-chat' ); ?></p></td></tr>
				<tr><th scope="row"><label for="epac_custom_auth_prefix"><?php echo esc_html__( 'Authorization Prefix', 'edupaper-ai-chat' ); ?></label></th><td><input id="epac_custom_auth_prefix" class="regular-text" type="text" name="<?php echo esc_attr( EPAC_OPTION_NAME ); ?>[custom_auth_prefix]" value="<?php echo esc_attr( $settings['custom_auth_prefix'] ); ?>" /><p class="description"><?php echo esc_html__( 'Usually Bearer.', 'edupaper-ai-chat' ); ?></p></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Register frontend assets.
 *
 * @return void
 */
function epac_register_assets() {
	wp_register_style(
		'epac-frontend',
		EPAC_PLUGIN_URL . 'assets/css/edupaper-ai-chat.css',
		array(),
		EPAC_VERSION
	);

	wp_register_script(
		'epac-frontend',
		EPAC_PLUGIN_URL . 'assets/js/edupaper-ai-chat.js',
		array(),
		EPAC_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'epac_register_assets' );

/**
 * Return enabled providers for frontend.
 *
 * @return array<int, array<string, string>>
 */
function epac_get_enabled_providers_for_frontend() {
	$settings = epac_get_settings();
	$labels   = epac_provider_labels();
	$enabled  = array();

	foreach ( $labels as $key => $label ) {
		$enabled_key = $key . '_enabled';
		if ( '1' === (string) $settings[ $enabled_key ] ) {
			$enabled[] = array(
				'value' => $key,
				'label' => 'custom' === $key && ! empty( $settings['custom_provider_name'] ) ? (string) $settings['custom_provider_name'] : $label,
			);
		}
	}

	return $enabled;
}

/**
 * Enqueue frontend assets only when shortcode is rendered.
 *
 * @return void
 */
function epac_enqueue_frontend_assets() {
	$settings = epac_get_settings();

	wp_enqueue_style( 'epac-frontend' );
	wp_enqueue_script( 'epac-frontend' );

	wp_localize_script(
		'epac-frontend',
		'EPAC_DATA',
		array(
			'endpoint'            => esc_url_raw( rest_url( EPAC_REST_NAMESPACE . '/generate' ) ),
			'nonce'               => wp_create_nonce( 'epac_chat_nonce' ),
			'defaultProvider'     => sanitize_key( $settings['default_provider'] ),
			'showProviderSwitch'  => '1' === (string) $settings['show_provider_switch'],
			'enabledProviders'    => epac_get_enabled_providers_for_frontend(),
			'i18n'                => array(
				'copied'      => __( 'Prompt copied.', 'edupaper-ai-chat' ),
				'working'     => __( 'Generating paper...', 'edupaper-ai-chat' ),
				'error'       => __( 'Something went wrong. Please try again.', 'edupaper-ai-chat' ),
				'emptyPrompt' => __( 'Please select options or add instructions first.', 'edupaper-ai-chat' ),
				'assistantHi' => __( 'Select the paper options above. Your prompt will appear below, then I can generate the paper here.', 'edupaper-ai-chat' ),
			),
		)
	);
}

/**
 * Return dropdown configuration.
 *
 * @return array<string, array<string, mixed>>
 */
function epac_get_dropdowns() {
	return array(
		'grade'        => array(
			'label'   => __( 'Grade', 'edupaper-ai-chat' ),
			'default' => 'Grade 9',
			'options' => array( 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12' ),
		),
		'subject'      => array(
			'label'   => __( 'Subject', 'edupaper-ai-chat' ),
			'default' => 'Physics',
			'options' => array( 'Mathematics', 'English', 'Urdu', 'Science', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Islamiat', 'Pakistan Studies' ),
		),
		'board'        => array(
			'label'   => __( 'Board', 'edupaper-ai-chat' ),
			'default' => 'FBISE (Federal)',
			'options' => array( 'PCTB (Punjab)', 'FBISE (Federal)', 'Sindh Board', 'KPK Board', 'Balochistan Board', 'AKUEB' ),
		),
		'testType'     => array(
			'label'   => __( 'Test Type', 'edupaper-ai-chat' ),
			'default' => 'Formative',
			'options' => array( 'Formative', 'Summative', 'Diagnostic' ),
		),
		'questionType' => array(
			'label'   => __( 'Question Type', 'edupaper-ai-chat' ),
			'default' => 'MCQs',
			'options' => array( 'MCQs', 'Short Questions', 'Long Questions', 'Practical/Diagram', 'Moral/Ethical', 'Islamic' ),
		),
		'duration'     => array(
			'label'   => __( 'Duration', 'edupaper-ai-chat' ),
			'default' => '45 minutes',
			'options' => array( '30 minutes', '45 minutes', '60 minutes', '90 minutes', '120 minutes' ),
		),
		'language'     => array(
			'label'   => __( 'Language', 'edupaper-ai-chat' ),
			'default' => 'English',
			'options' => array( 'English', 'Urdu', 'Bilingual' ),
		),
		'bloom'        => array(
			'label'   => __( 'Bloom', 'edupaper-ai-chat' ),
			'default' => 'No',
			'options' => array( 'No', 'Yes (Varied Levels)' ),
		),
		'bloomLevel'   => array(
			'label'   => __( 'Bloom Level', 'edupaper-ai-chat' ),
			'default' => 'Not specified',
			'options' => array( 'Not specified', 'Knowledge', 'Comprehension', 'Application', 'Analysis', 'Synthesis', 'Evaluation', 'Mixed/Varied' ),
		),
		'mcqs'         => array(
			'label'   => __( 'MCQs', 'edupaper-ai-chat' ),
			'default' => '20',
			'options' => array( '5', '10', '15', '20', '25', '30', '40', '50' ),
		),
		'difficulty'   => array(
			'label'   => __( 'Difficulty', 'edupaper-ai-chat' ),
			'default' => 'Mixed',
			'options' => array( 'Easy', 'Moderate', 'Hard', 'Mixed' ),
		),
	);
}

/**
 * Render one custom dropdown.
 *
 * @param string               $key Dropdown key.
 * @param array<string, mixed> $config Dropdown config.
 * @return void
 */
function epac_render_dropdown( $key, $config ) {
	$label   = isset( $config['label'] ) ? (string) $config['label'] : $key;
	$default = isset( $config['default'] ) ? (string) $config['default'] : '';
	$options = isset( $config['options'] ) && is_array( $config['options'] ) ? $config['options'] : array();
	$uid     = 'epac-' . sanitize_html_class( $key ) . '-' . wp_rand( 1000, 9999 );
	?>
	<div class="epac-dropdown" data-epac-dropdown data-epac-field="<?php echo esc_attr( $key ); ?>" data-epac-label="<?php echo esc_attr( $label ); ?>" data-epac-value="<?php echo esc_attr( $default ); ?>">
		<button id="<?php echo esc_attr( $uid ); ?>" class="epac-dropdown__toggle" type="button" aria-haspopup="listbox" aria-expanded="false">
			<span class="epac-dropdown__label"><?php echo esc_html( $label ); ?></span>
			<span class="epac-dropdown__value" data-epac-current><?php echo esc_html( $default ); ?></span>
			<span class="epac-dropdown__chevron" aria-hidden="true">⌄</span>
		</button>
		<div class="epac-dropdown__menu" role="listbox" aria-labelledby="<?php echo esc_attr( $uid ); ?>">
			<?php foreach ( $options as $option ) : ?>
				<button class="epac-dropdown__option" type="button" role="option" data-epac-option="<?php echo esc_attr( $option ); ?>" aria-selected="<?php echo esc_attr( $option === $default ? 'true' : 'false' ); ?>">
					<?php echo esc_html( $option ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render provider picker for frontend.
 *
 * @return void
 */
function epac_render_provider_picker() {
	$settings = epac_get_settings();
	$enabled  = epac_get_enabled_providers_for_frontend();

	if ( '1' !== (string) $settings['show_provider_switch'] || count( $enabled ) < 2 ) {
		return;
	}
	?>
	<div class="epac-provider-picker" data-epac-provider-picker>
		<span class="epac-provider-picker__label"><?php echo esc_html__( 'AI Provider', 'edupaper-ai-chat' ); ?></span>
		<div class="epac-provider-picker__buttons" role="radiogroup" aria-label="<?php echo esc_attr__( 'Choose AI provider', 'edupaper-ai-chat' ); ?>">
			<?php foreach ( $enabled as $provider ) : ?>
				<button class="epac-provider-picker__button" type="button" data-epac-provider="<?php echo esc_attr( $provider['value'] ); ?>" role="radio" aria-checked="<?php echo esc_attr( $provider['value'] === $settings['default_provider'] ? 'true' : 'false' ); ?>">
					<?php echo esc_html( $provider['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render shortcode.
 *
 * Usage: [edupaper_ai_chat] or [edupaper_ai_chat full_width="no"]
 * Backward-compatible alias: [edupaper_grok_chat]
 *
 * @param array<string, mixed> $atts Shortcode attributes.
 * @return string
 */
function epac_render_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'full_width' => 'yes',
		),
		$atts,
		'edupaper_ai_chat'
	);

	epac_enqueue_frontend_assets();

	$dropdowns  = epac_get_dropdowns();
	$full_width = 'yes' === strtolower( (string) $atts['full_width'] );
	$classes    = 'epac-app' . ( $full_width ? ' epac-app--full-width' : '' );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" data-epac-root>
		<div class="epac-shell">
			<header class="epac-header">
				<p class="epac-kicker"><?php echo esc_html__( 'EduPaper AI Chat', 'edupaper-ai-chat' ); ?></p>
				<h2 class="epac-title"><?php echo esc_html__( 'Smart Exam Paper Designer', 'edupaper-ai-chat' ); ?></h2>
				<p class="epac-subtitle"><?php echo esc_html__( 'Choose options, review the prompt, then generate an exam paper with your selected AI provider.', 'edupaper-ai-chat' ); ?></p>
			</header>

			<?php epac_render_provider_picker(); ?>

			<section class="epac-controls" aria-label="<?php echo esc_attr__( 'Paper options', 'edupaper-ai-chat' ); ?>">
				<?php
				foreach ( $dropdowns as $key => $config ) {
					epac_render_dropdown( $key, $config );
				}
				?>
			</section>

			<section class="epac-chat" aria-label="<?php echo esc_attr__( 'Chat output', 'edupaper-ai-chat' ); ?>">
				<div class="epac-chat__messages" data-epac-messages aria-live="polite" aria-atomic="false">
					<div class="epac-message epac-message--assistant">
						<div class="epac-message__avatar" aria-hidden="true">AI</div>
						<div class="epac-message__bubble"><?php echo esc_html__( 'Select the paper options above. Your prompt will appear below, then I can generate the paper here.', 'edupaper-ai-chat' ); ?></div>
					</div>
				</div>
			</section>

			<section class="epac-composer" aria-label="<?php echo esc_attr__( 'Prompt composer', 'edupaper-ai-chat' ); ?>">
				<label class="epac-composer__label" for="epac-extra-instructions"><?php echo esc_html__( 'Topic / extra instructions', 'edupaper-ai-chat' ); ?></label>
				<textarea id="epac-extra-instructions" class="epac-composer__extra" data-epac-extra rows="2" placeholder="<?php echo esc_attr__( 'Example: Unit 3–4, include answer key and marks distribution.', 'edupaper-ai-chat' ); ?>"></textarea>

				<label class="epac-composer__label" for="epac-built-prompt"><?php echo esc_html__( 'User prompt built from selections', 'edupaper-ai-chat' ); ?></label>
				<textarea id="epac-built-prompt" class="epac-composer__prompt" data-epac-prompt rows="4" readonly></textarea>

				<div class="epac-composer__actions">
					<button class="epac-button epac-button--secondary" type="button" data-epac-copy><?php echo esc_html__( 'Copy Prompt', 'edupaper-ai-chat' ); ?></button>
					<button class="epac-button" type="button" data-epac-send><?php echo esc_html__( 'Generate', 'edupaper-ai-chat' ); ?></button>
				</div>
				<p class="epac-privacy-note"><?php echo esc_html__( 'When you click Generate, the prompt is sent to the selected external AI API to create the response.', 'edupaper-ai-chat' ); ?></p>
				<p class="epac-notice" data-epac-notice role="status" aria-live="polite"></p>
			</section>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'edupaper_ai_chat', 'epac_render_shortcode' );
add_shortcode( 'edupaper_grok_chat', 'epac_render_shortcode' );

/**
 * Add privacy policy suggestion text.
 *
 * @return void
 */
function epac_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = sprintf(
		'<p>%s</p>',
		esc_html__( 'EduPaper AI Chat sends the prompt text entered or generated by the visitor to the selected external AI provider only when the visitor clicks the generate button. The plugin stores the site owner\'s API keys in WordPress options and does not store visitor prompts by default.', 'edupaper-ai-chat' )
	);

	wp_add_privacy_policy_content( 'EduPaper AI Chat', wp_kses_post( $content ) );
}
add_action( 'admin_init', 'epac_add_privacy_policy_content' );

/**
 * Register REST route.
 *
 * @return void
 */
function epac_register_rest_routes() {
	register_rest_route(
		EPAC_REST_NAMESPACE,
		'/generate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'epac_rest_generate',
			'permission_callback' => '__return_true',
			'args'                => array(
				'prompt'   => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
				'provider' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'epac_register_rest_routes' );

/**
 * Basic public rate limit by IP.
 *
 * @return true|WP_Error
 */
function epac_check_rate_limit() {
	$settings = epac_get_settings();
	$limit    = isset( $settings['rate_limit_per_10_min'] ) ? absint( $settings['rate_limit_per_10_min'] ) : 20;
	$limit    = max( 1, min( 200, $limit ) );
	$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key      = 'epac_rate_' . md5( $ip );
	$count    = (int) get_transient( $key );

	if ( $count >= $limit ) {
		return new WP_Error(
			'epac_rate_limited',
			__( 'Too many requests. Please try again later.', 'edupaper-ai-chat' ),
			array( 'status' => 429 )
		);
	}

	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

/**
 * Validate provider key.
 *
 * @param string               $provider Provider key.
 * @param array<string, mixed> $settings Settings.
 * @return string|WP_Error
 */
function epac_resolve_provider( $provider, $settings ) {
	$provider = sanitize_key( $provider );
	$labels   = epac_provider_labels();

	if ( ! isset( $labels[ $provider ] ) ) {
		$provider = sanitize_key( $settings['default_provider'] );
	}

	if ( empty( $labels[ $provider ] ) || '1' !== (string) $settings[ $provider . '_enabled' ] ) {
		return new WP_Error(
			'epac_provider_disabled',
			__( 'The selected AI provider is not enabled.', 'edupaper-ai-chat' ),
			array( 'status' => 400 )
		);
	}

	return $provider;
}

/**
 * Prevent unsafe custom URLs.
 *
 * @param string $url Remote URL.
 * @return bool
 */
function epac_is_safe_remote_url( $url ) {
	$url = esc_url_raw( $url );
	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		return false;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}

	$host = strtolower( $parts['host'] );
	if ( in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) ) {
		return false;
	}

	$ip = gethostbyname( $host );
	if ( $ip === $host && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return true;
	}

	return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Extract text from a Chat Completions-style response.
 *
 * @param array<string, mixed> $data Response data.
 * @return string
 */
function epac_extract_chat_completion_text( $data ) {
	if ( isset( $data['choices'][0]['message']['content'] ) ) {
		$content = $data['choices'][0]['message']['content'];
		if ( is_string( $content ) ) {
			return $content;
		}
		if ( is_array( $content ) ) {
			$text = '';
			foreach ( $content as $part ) {
				if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
			return $text;
		}
	}

	if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
		return $data['output_text'];
	}

	return '';
}

/**
 * Extract text from an OpenAI Responses API response.
 *
 * @param array<string, mixed> $data Response data.
 * @return string
 */
function epac_extract_openai_response_text( $data ) {
	if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
		return $data['output_text'];
	}

	$text = '';
	if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
		foreach ( $data['output'] as $item ) {
			if ( empty( $item['content'] ) || ! is_array( $item['content'] ) ) {
				continue;
			}
			foreach ( $item['content'] as $content ) {
				if ( isset( $content['text'] ) && is_string( $content['text'] ) ) {
					$text .= $content['text'];
				}
			}
		}
	}

	return $text;
}

/**
 * Extract text from Gemini generateContent response.
 *
 * @param array<string, mixed> $data Response data.
 * @return string
 */
function epac_extract_gemini_text( $data ) {
	$text = '';
	if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
		foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
			if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
	}

	return $text;
}

/**
 * Send JSON request and normalize errors.
 *
 * @param string               $url Remote URL.
 * @param array<string, mixed> $headers Request headers.
 * @param array<string, mixed> $body Request body.
 * @param string               $fallback_error Fallback error message.
 * @return array<string, mixed>|WP_Error
 */
function epac_remote_json_post( $url, $headers, $body, $fallback_error ) {
	if ( ! epac_is_safe_remote_url( $url ) ) {
		return new WP_Error(
			'epac_unsafe_remote_url',
			__( 'The configured API endpoint is not allowed. Use a public HTTPS API endpoint.', 'edupaper-ai-chat' ),
			array( 'status' => 500 )
		);
	}

	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'epac_remote_error',
			$response->get_error_message(),
			array( 'status' => 502 )
		);
	}

	$status_code = (int) wp_remote_retrieve_response_code( $response );
	$raw_body    = wp_remote_retrieve_body( $response );
	$data        = json_decode( $raw_body, true );
	$data        = is_array( $data ) ? $data : array();

	if ( $status_code < 200 || $status_code >= 300 ) {
		$message = '';
		if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			$message = $data['error']['message'];
		} elseif ( isset( $data['error']['status'] ) && is_string( $data['error']['status'] ) ) {
			$message = $data['error']['status'];
		}
		$message = '' !== $message ? sanitize_text_field( $message ) : $fallback_error;

		return new WP_Error(
			'epac_api_error',
			$message,
			array( 'status' => $status_code )
		);
	}

	return $data;
}

/**
 * Call OpenAI Responses API.
 *
 * @param string               $prompt User prompt.
 * @param array<string, mixed> $settings Settings.
 * @return string|WP_Error
 */
function epac_call_openai( $prompt, $settings ) {
	if ( empty( $settings['openai_api_key'] ) ) {
		return new WP_Error( 'epac_missing_openai_key', __( 'OpenAI API key is missing.', 'edupaper-ai-chat' ), array( 'status' => 500 ) );
	}

	$body = array(
		'model'        => sanitize_text_field( $settings['openai_model'] ),
		'instructions' => (string) $settings['system_prompt'],
		'input'        => $prompt,
		'temperature'  => (float) $settings['temperature'],
		'max_output_tokens' => (int) $settings['max_output_tokens'],
	);

	$data = epac_remote_json_post(
		(string) $settings['openai_endpoint'],
		array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $settings['openai_api_key'],
		),
		$body,
		__( 'OpenAI API request failed.', 'edupaper-ai-chat' )
	);

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return epac_extract_openai_response_text( $data );
}

/**
 * Call Gemini generateContent API.
 *
 * @param string               $prompt User prompt.
 * @param array<string, mixed> $settings Settings.
 * @return string|WP_Error
 */
function epac_call_gemini( $prompt, $settings ) {
	if ( empty( $settings['gemini_api_key'] ) ) {
		return new WP_Error( 'epac_missing_gemini_key', __( 'Gemini API key is missing.', 'edupaper-ai-chat' ), array( 'status' => 500 ) );
	}

	$base  = untrailingslashit( (string) $settings['gemini_endpoint_base'] );
	$model = rawurlencode( sanitize_text_field( $settings['gemini_model'] ) );
	$url   = add_query_arg( 'key', rawurlencode( (string) $settings['gemini_api_key'] ), $base . '/models/' . $model . ':generateContent' );

	$body = array(
		'systemInstruction' => array(
			'parts' => array(
				array( 'text' => (string) $settings['system_prompt'] ),
			),
		),
		'contents'          => array(
			array(
				'role'  => 'user',
				'parts' => array(
					array( 'text' => $prompt ),
				),
			),
		),
		'generationConfig'  => array(
			'temperature'     => (float) $settings['temperature'],
			'maxOutputTokens' => (int) $settings['max_output_tokens'],
		),
	);

	$data = epac_remote_json_post(
		$url,
		array( 'Content-Type' => 'application/json' ),
		$body,
		__( 'Gemini API request failed.', 'edupaper-ai-chat' )
	);

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return epac_extract_gemini_text( $data );
}

/**
 * Call xAI/Grok Chat Completions API.
 *
 * @param string               $prompt User prompt.
 * @param array<string, mixed> $settings Settings.
 * @return string|WP_Error
 */
function epac_call_xai( $prompt, $settings ) {
	if ( empty( $settings['xai_api_key'] ) ) {
		return new WP_Error( 'epac_missing_xai_key', __( 'xAI API key is missing.', 'edupaper-ai-chat' ), array( 'status' => 500 ) );
	}

	$body = array(
		'model'       => sanitize_text_field( $settings['xai_model'] ),
		'messages'    => array(
			array(
				'role'    => 'system',
				'content' => (string) $settings['system_prompt'],
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		),
		'temperature' => (float) $settings['temperature'],
		'max_tokens'  => (int) $settings['max_output_tokens'],
	);

	$data = epac_remote_json_post(
		(string) $settings['xai_endpoint'],
		array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $settings['xai_api_key'],
		),
		$body,
		__( 'xAI API request failed.', 'edupaper-ai-chat' )
	);

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return epac_extract_chat_completion_text( $data );
}

/**
 * Call a custom OpenAI-compatible Chat Completions endpoint.
 *
 * @param string               $prompt User prompt.
 * @param array<string, mixed> $settings Settings.
 * @return string|WP_Error
 */
function epac_call_custom( $prompt, $settings ) {
	if ( empty( $settings['custom_api_key'] ) ) {
		return new WP_Error( 'epac_missing_custom_key', __( 'Custom API key is missing.', 'edupaper-ai-chat' ), array( 'status' => 500 ) );
	}

	if ( empty( $settings['custom_endpoint'] ) ) {
		return new WP_Error( 'epac_missing_custom_endpoint', __( 'Custom API endpoint is missing.', 'edupaper-ai-chat' ), array( 'status' => 500 ) );
	}

	$body = array(
		'model'       => sanitize_text_field( $settings['custom_model'] ),
		'messages'    => array(
			array(
				'role'    => 'system',
				'content' => (string) $settings['system_prompt'],
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		),
		'temperature' => (float) $settings['temperature'],
		'max_tokens'  => (int) $settings['max_output_tokens'],
	);

	$headers = array( 'Content-Type' => 'application/json' );
	$prefix  = sanitize_text_field( $settings['custom_auth_prefix'] );
	if ( ! empty( $settings['custom_api_key'] ) ) {
		$headers['Authorization'] = trim( $prefix . ' ' . $settings['custom_api_key'] );
	}

	$data = epac_remote_json_post(
		(string) $settings['custom_endpoint'],
		$headers,
		$body,
		__( 'Custom API request failed.', 'edupaper-ai-chat' )
	);

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return epac_extract_chat_completion_text( $data );
}

/**
 * Handle generation request.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function epac_rest_generate( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x-epac-nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'epac_chat_nonce' ) ) {
		return new WP_Error(
			'epac_bad_nonce',
			__( 'Security check failed. Please refresh the page and try again.', 'edupaper-ai-chat' ),
			array( 'status' => 403 )
		);
	}

	$rate_limit = epac_check_rate_limit();
	if ( is_wp_error( $rate_limit ) ) {
		return $rate_limit;
	}

	$settings = epac_get_settings();
	if ( '1' !== (string) $settings['external_consent'] ) {
		return new WP_Error(
			'epac_missing_consent',
			__( 'External API consent is not enabled in plugin settings.', 'edupaper-ai-chat' ),
			array( 'status' => 500 )
		);
	}

	$prompt = (string) $request->get_param( 'prompt' );
	$prompt = trim( $prompt );
	if ( '' === $prompt ) {
		return new WP_Error(
			'epac_empty_prompt',
			__( 'Prompt cannot be empty.', 'edupaper-ai-chat' ),
			array( 'status' => 400 )
		);
	}

	if ( strlen( $prompt ) > 8000 ) {
		return new WP_Error(
			'epac_prompt_too_long',
			__( 'Prompt is too long. Please shorten it.', 'edupaper-ai-chat' ),
			array( 'status' => 400 )
		);
	}

	$requested_provider = (string) $request->get_param( 'provider' );
	$provider           = epac_resolve_provider( $requested_provider, $settings );
	if ( is_wp_error( $provider ) ) {
		return $provider;
	}

	switch ( $provider ) {
		case 'openai':
			$answer = epac_call_openai( $prompt, $settings );
			break;
		case 'gemini':
			$answer = epac_call_gemini( $prompt, $settings );
			break;
		case 'custom':
			$answer = epac_call_custom( $prompt, $settings );
			break;
		case 'xai':
		default:
			$answer = epac_call_xai( $prompt, $settings );
			break;
	}

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	if ( '' === trim( (string) $answer ) ) {
		return new WP_Error(
			'epac_empty_response',
			__( 'The API returned an empty response.', 'edupaper-ai-chat' ),
			array( 'status' => 502 )
		);
	}

	return rest_ensure_response(
		array(
			'answer'   => wp_kses_post( (string) $answer ),
			'provider' => sanitize_key( $provider ),
		)
	);
}
