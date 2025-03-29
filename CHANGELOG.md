# Changelog

All notable changes to the Multi-LLM Chatbot plugin will be documented in this file.

## [1.35.0] - 2024-02-25
### Fixed
- Fixed mobile viewport issues with virtual keyboard
- Improved header positioning on mobile devices
- Added proper viewport meta tag with interactive-widget support
- Fixed header layout to prevent white bar at top
- Enhanced flex layout for better mobile responsiveness

## [1.34.0] - 2024-02-25
### Added
- WYSIWYG markdown editor for AI instructions
- Improved formatting capabilities for system prompts
- Visual markdown editing with preview functionality

## [1.33.0] - 2024-02-24
### Fixed
- Fixed Claude API integration with proper message formatting
- Updated Claude model to claude-3-haiku-20240307
- Added proper streaming response handling for Claude
- Fixed token counting for Claude responses

## [1.32.0] - 2024-02-24
### Changed
- Fixed pricing calculations to use per 1M tokens instead of per 1K tokens
- Added detailed cost comparison in debug logs
- Removed request body logging from debug output
- Updated Mistral model names (mistral-nemo -> open-mistral-nemo)

## [1.31.0] - 2024-02-23
### Added
- Cost tracking and logging functionality
- Debug mode toggle in admin settings
- System prompt token counting in cost calculations

### Changed
- Improved field visibility handling in admin interface
- Better organization of model selection and assistant fields
- Changed chatbot access control from admin-only to registered-users-only
- Added debug mode option to control logging verbosity

### Fixed
- Fixed assistant ID field visibility when switching providers
- Fixed instructions field visibility across all providers
- Added warning message for Mistral Agent API limitations
- Improved UI consistency for assistant mode toggles

## [1.30.0] - 2025-02-22
### Added
- Model selection for OpenAI standard API with model fetching capability
- Added pricing information for OpenAI models in selection dropdown
- Added model selection and pricing for Mistral standard API

### Fixed
- Model selection now persists for both OpenAI and Mistral
- Selected model is preserved when fetching available models
- Added persistence for fetched models list in database
- Fixed model selection visibility when switching providers
- Fixed duplicate model dropdowns with assistant toggle
- Improved visibility handling for assistant/agent fields and instructions
- Unified chatbot instructions across all providers

## [1.29.0] - 2025-02-22
### Added
- Warning message in admin interface about Mistral Agent API's limited conversation history support

### Fixed
- Improved logging for API responses
- Better error handling for JSON decoding
- Added HTML escaping for user messages to prevent XSS attacks
- Restored clear message labels for users and assistant responses
- Simplified context handling - now automatic when enabled in admin

## [1.28.0] - 2025-02-21
### Added
- Support for Mistral Agent API

### Changed
- Improved message handling for Mistral Agent
- Enhanced history tracking across sessions
- Better UI feedback for messages

## [1.27.0] - 2025-02-20
### Fixed
- Mistral agent integration now properly uses the agents API endpoint
- Removed unnecessary system message handling for Mistral agents
- Cleaned up logging and error handling

## [1.26.0] - 2025-02-20
### Added
- Better CORS handling for production environments
- Improved error logging for API requests
- Enhanced SSE error handling

### Fixed
- Production server compatibility issues
- EventSource connection errors
- Mistral agent handling in production

## [1.25.0] - 2025-02-20
### Changed
- Reverted compression-based context handling
- Restored smart truncation for better performance
- Kept 8000 character limit for larger context

### Fixed
- Context processing speed issues
- LLM response time with context
- Better handling of large Elementor content

## [1.24.0] - 2025-02-20
### Added
- Content compression for more efficient context handling
- Smart content compression with LLM instructions
- Better context size optimization

### Changed
- Context handling now uses gzip compression
- Improved content size logging
- Better content cleanup before compression

### Fixed
- Context length limitations
- Content truncation issues
- Large page content handling

## [1.23.0] - 2025-02-20
### Changed
- Replaced context button with checkbox for better usability
- Updated all UI text to French
- Improved context toggle visibility and understanding
- Better layout for context controls

### Fixed
- French translations for all user messages
- Context toggle user experience
- Error message formatting in French

## [1.22.0] - 2025-02-20
### Added
- Global context awareness toggle in settings
- Better control over context feature visibility

### Changed
- Context button only shows when enabled in settings
- Improved context handling based on global settings
- Better user experience for context management

### Fixed
- Context button visibility logic
- Context handling respects global settings
- Better error handling for context state

## [1.21.0] - 2025-02-20
### Fixed
- Mistral assistant API integration
- Context handling for Mistral agents
- Proper API endpoint usage for each provider
- Better error handling for Mistral requests

### Changed
- Separated OpenAI and Mistral assistant handling
- Improved context integration for Mistral
- Better logging for API requests

## [1.20.0] - 2025-02-19
### Added
- Enhanced page context with categories and tags
- Support for page hierarchy in context
- Comments integration in context
- Custom fields (ACF) support
- Publication date and metadata
- Smart context truncation preserving sentences

### Changed
- Improved context structure for better AI understanding
- Better organization of contextual information
- More comprehensive page awareness

### Fixed
- Context truncation preserving sentence boundaries
- Better handling of custom field data
- Improved context relevance

## [1.19.0] - 2025-02-19
### Added
- Context toggle button in chatbot interface
- Visual feedback for context state
- Dynamic context button visibility

### Changed
- Moved context control from settings to chatbot UI
- Improved context toggle user experience
- Better visual indication of context status

### Fixed
- Context awareness more intuitive to use
- Context button hidden when no context available
- Context state feedback

## [1.18.0] - 2025-02-19
### Added
- Page context awareness: chatbot now has access to current page content
- Automatic context injection into chat conversations
- Smart context truncation to avoid API limits

### Changed
- Enhanced system instructions to incorporate page context
- Improved context handling for better response relevance

## [1.17.0] - 2025-02-19
### Added
- Chat history persistence across page loads
- Clear chat button to start new conversations
- Confirmation dialog before clearing chat history
- Automatic saving of chat messages

### Changed
- Improved chat header layout
- Enhanced chat history management
- Better user experience for chat clearing

### Fixed
- Chat scroll position after loading history
- Message saving reliability

## [1.16.0] - 2025-02-18
### Changed
- Improved chatbot UI and animations
- Adjusted chatbot position and size for better usability
- Added smooth transitions and animations
- Added click-outside-to-minimize behavior
- Standardized font sizes to 14px
- Better mobile responsiveness

### Fixed
- Fixed chatbot positioning on smaller screens
- Fixed jQuery dependency issues
- Improved animation performance
- Better handling of chat container states

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