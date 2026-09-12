import Foundation
import Combine

@MainActor
final class APIConfiguration: ObservableObject {
    static let defaultBaseURL = "https://api.openai.com/v1"
    static let defaultModel = "gpt-4o-mini"

    private enum Keys {
        static let baseURL = "api.baseURL"
        static let model = "api.model"
    }

    @Published var baseURL: String {
        didSet { UserDefaults.standard.set(baseURL, forKey: Keys.baseURL) }
    }

    @Published var model: String {
        didSet { UserDefaults.standard.set(model, forKey: Keys.model) }
    }

    @Published private(set) var hasAPIKey: Bool

    init() {
        let storedURL = UserDefaults.standard.string(forKey: Keys.baseURL)?
            .trimmingCharacters(in: .whitespacesAndNewlines)
        baseURL = (storedURL?.isEmpty == false) ? storedURL! : Self.defaultBaseURL

        let storedModel = UserDefaults.standard.string(forKey: Keys.model)?
            .trimmingCharacters(in: .whitespacesAndNewlines)
        model = (storedModel?.isEmpty == false) ? storedModel! : Self.defaultModel

        hasAPIKey = KeychainStore.loadAPIKey() != nil
    }

    var apiKey: String? {
        KeychainStore.loadAPIKey()
    }

    func saveAPIKey(_ raw: String) throws {
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        if trimmed.isEmpty {
            KeychainStore.deleteAPIKey()
            hasAPIKey = false
            return
        }
        try KeychainStore.saveAPIKey(trimmed)
        hasAPIKey = true
    }

    func clearAPIKey() {
        KeychainStore.deleteAPIKey()
        hasAPIKey = false
    }

    func makeClient() throws -> LLMClient {
        guard let key = apiKey, !key.isEmpty else { throw AppError.missingAPIKey }
        return try LLMClient(baseURLString: baseURL, apiKey: key, model: model)
    }
}
