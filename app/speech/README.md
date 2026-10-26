# fusionpbx-app-speech
Text to Speech

## Install
```
apt install sox libsox-fmt-all
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-speech.git speech
chown -R www-data:www-data /var/www/fusionpbx
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

## Define the required Settings
- Menu -> Default Settings 
- Category: speech

### Microsoft Azure Speech (Foundry Tools)

Run the FusionPBX upgrade after installing or updating this app to register the
settings, then enable and configure these entries in the `speech` category:

| Setting | Value |
| --- | --- |
| `enabled` | `true` |
| `engine` | `azure` |
| `api_key` | The key for your Azure Speech / Foundry resource |
| `region` | The resource's Azure region identifier, for example `eastus` |

The resource must support Azure Speech, with key authentication enabled. The key
and region must belong to the same resource. This engine uses public Azure
regional endpoints (`https://<region>.tts.speech.microsoft.com`); `api_url` is
not used. Private endpoints, sovereign clouds, custom voice deployments, and
Azure OpenAI deployments are not supported by this engine.

Available voices are fetched from Azure for the configured region and filtered
to US English (`en-US`) only. Select a
voice such as `en-US-JennyNeural`; its short name determines the language.
Text is escaped as plain text inside SSML. Speed control supports 0.5x to 2.0x.
There is no separate model selector or translation support.

Audio is saved as a `.wav` file (24 kHz, 16-bit, mono PCM), using the requested
filename's stem. API, configuration, invalid-response, and file-write failures
are logged through PHP's error log; synthesis returns `false` on failure.

API reference: [Azure Speech REST text to speech](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-text-to-speech).

## Tests

Run `php -n tests/speech_azure_test.php` to test Azure request construction,
voice discovery, synthesis, and failure handling without credentials or network
access. The `-n` flag disables extensions so the test can stub cURL.
