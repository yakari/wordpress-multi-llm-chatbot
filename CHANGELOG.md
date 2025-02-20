# Changelog

All notable changes to the Multi-LLM Chatbot plugin will be documented in this file.

## [1.15.0] - 2025-02-18
### Added
- Support for Claude API integration
- Support for Google Gemini API integration
- Support for Perplexity API integration

### Warning
⚠️ The following integrations have not been tested yet:
- Claude (Anthropic)
- Google Gemini
- Perplexity

Please report any issues on the plugin's GitHub repository.

### Changed
- Standardized streaming response handling across all providers
- Improved error handling for API responses
- Updated API endpoint configurations

## [1.14.0] - 2025-02-18
### Changed
- Improved definition field behavior in assistant/agent mode
- Made definition field readonly instead of disabled to preserve content
- Consistent state handling between page loads and provider switches
- Better visual feedback for definition field states

### Fixed
- Definition content no longer lost when toggling assistant mode
- Definition field state consistency after form submission
- Visual state consistency between PHP and JavaScript handling

## [1.13.0] - 2025-02-18
### Changed
- Simplified assistant/agent handling
- Removed OpenAI definition fetching
- Made definition behavior consistent across providers
- Improved UI for definition field state management

### Removed
- OpenAI assistant definition fetching
- Assistant definition updating

## [1.12.0] - 2025-02-18
### Added
- Toggle switch for Assistant/Agent API usage
- Automatic API type switching
- Preserved assistant IDs when disabled

### Changed
- Improved admin interface for API selection
- Better handling of API type switching
- Enhanced user experience for API configuration

## [1.11.0] - 2025-02-18
### Added
- Fallback to chat API when no assistant ID is provided
- System instructions support for direct API usage
- Flexible switching between assistant and chat APIs

### Changed
- Enhanced provider handling logic
- Improved API selection based on configuration
- Better user guidance for API options

## [1.10.0] - 2025-02-18
### Added
- Universal instructions field for all providers
- Provider-specific behavior definitions
- Fallback to direct API usage when no assistant ID

### Changed
- Made definition field always visible
- Improved field descriptions and usage hints
- Better handling of provider-specific settings

## [1.9.0] - 2025-02-18
### Added
- Markdown rendering support for assistant responses
- Code syntax highlighting
- Better formatting for lists, tables, and quotes
- Improved code block display

### Changed
- Enhanced message display with proper markdown styling
- Better handling of HTML escaping
- Improved response formatting

## [1.8.0] - 2025-02-18
### Changed
- OpenAI assistant instructions now save with form submission
- Removed automatic instructions updating
- Added proper error handling for instructions updates

### Fixed
- Settings synchronization with OpenAI
- Form submission behavior
- Error message display

## [1.7.0] - 2025-02-18
### Added
- Automatic OpenAI assistant definition fetching
- Real-time assistant instructions updating
- Simplified assistant management interface

### Changed
- Removed manual fetch button
- Streamlined OpenAI assistant configuration
- Improved user experience for assistant management

## [1.6.0] - 2025-02-18
### Changed
- Removed Mistral agent definition functionality (API not yet available)
- Restricted definition viewing/editing to OpenAI assistants only

### Fixed
- Mistral API endpoint handling
- Assistant definition UI logic

## [1.5.0] - 2025-02-18
### Added
- Assistant/Agent definition viewing and editing
- Fetch current definition functionality
- Separate definition storage for each provider
- Admin interface improvements

### Changed
- Enhanced settings management
- Better provider-specific configurations
- Improved admin UX

## [1.4.0] - 2025-02-18
### Added
- Provider-specific settings storage
- Separate API key storage for each provider
- Improved loading animations
- Better progress indicators
- Extended timeout to 60 seconds

### Changed
- Enhanced OpenAI assistant response handling
- Improved error messages and loading states
- Better status updates during processing

### Fixed
- OpenAI thread creation status check
- Loading indicator behavior
- Timeout message handling

## [1.3.0] - 2025-02-18
### Added
- Support for multiple API keys
- Individual assistant ID storage
- Dynamic settings fields

### Changed
- Improved admin interface
- Better provider switching
- Enhanced error handling

### Fixed
- Settings persistence issues
- Provider switching bugs
- Assistant ID validation

## [1.2.0] - 2025-02-18
### Added
- Support for Mistral AI Agents
- Streaming responses for all providers
- Better error handling and logging
- Local development support for LocalWP

### Changed
- Improved response handling for OpenAI assistants
- Enhanced error messages for better debugging
- Updated API integration for Claude-3

### Fixed
- Buffer handling for streaming responses
- CORS issues in local development
- Error handling for failed API requests

## [1.1.0] - 2025-02-17
### Added
- Support for Google Gemini
- Support for Perplexity AI
- Admin-only visibility option
- Enhanced error logging

### Changed
- Improved UI responsiveness
- Better handling of API timeouts

## [1.0.0] - 2025-02-17
### Added
- Initial release
- Support for OpenAI (GPT-4)
- Support for Claude
- Basic chat interface
- WordPress admin settings page
- API key configuration
- OpenAI Assistants API support