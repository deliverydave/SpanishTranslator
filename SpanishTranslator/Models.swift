import Foundation

enum ChatRole: String, Codable, Equatable {
    case user
    case assistant
}

struct ChatTurn: Identifiable, Codable, Equatable {
    var id: UUID
    var role: ChatRole
    var text: String
    var createdAt: Date

    init(id: UUID = UUID(), role: ChatRole, text: String, createdAt: Date = .now) {
        self.id = id
        self.role = role
        self.text = text
        self.createdAt = createdAt
    }
}

struct LuisContact: Codable, Equatable, Hashable {
    var identifier: String
    var displayName: String
    var phoneNumber: String

    var shortLabel: String {
        let name = displayName.trimmingCharacters(in: .whitespacesAndNewlines)
        if name.isEmpty { return phoneNumber }
        if phoneNumber.isEmpty { return name }
        return "\(name) · \(phoneNumber)"
    }

    var isSendable: Bool {
        !phoneNumber.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }
}

struct ComposeSnapshot: Codable, Equatable {
    var messages: [ChatTurn]
    var currentDraft: String
}

enum AppError: LocalizedError, Equatable {
    case missingAPIKey
    case invalidAPIURL
    case emptyInput
    case emptyDraft
    case emptyTranslation
    case noContact
    case noPhoneNumber
    case messagesUnavailable
    case ocrFailed
    case noTextInImage
    case network(String)
    case server(Int, String)
    case decoding
    case keychain
    case appleTranslationUnavailable

    var errorDescription: String? {
        switch self {
        case .missingAPIKey:
            return "Add an OpenAI-compatible API key in Settings to compose and polish texts."
        case .invalidAPIURL:
            return "The API base URL looks invalid. Example: https://api.openai.com/v1"
        case .emptyInput:
            return "Type a note or tweak first."
        case .emptyDraft:
            return "Polish an English draft before translating."
        case .emptyTranslation:
            return "The translator returned an empty result. Try again."
        case .noContact:
            return "Pick Luis (or another contact) in Settings so Messages knows who to text."
        case .noPhoneNumber:
            return "That contact has no phone number. Choose another, or enter a number in Settings."
        case .messagesUnavailable:
            return "This device cannot open the Messages composer. The Spanish text was copied so you can paste it."
        case .ocrFailed:
            return "Could not read text from that image. Try a sharper screenshot of the message bubble."
        case .noTextInImage:
            return "No text was found in that image. Crop closer to the message bubble and try again."
        case .network(let message):
            return message
        case .server(let code, let message):
            return "API error \(code): \(message)"
        case .decoding:
            return "The API response could not be read. Check the model name and base URL in Settings."
        case .keychain:
            return "Could not save the API key in the Keychain."
        case .appleTranslationUnavailable:
            return "On-device Apple Translation needs iOS 18 or later. Add an API key in Settings to translate here."
        }
    }
}
