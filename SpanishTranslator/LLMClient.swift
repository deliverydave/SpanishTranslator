import Foundation

struct LLMMessage: Equatable {
    enum Role: String {
        case system
        case user
        case assistant
    }

    var role: Role
    var content: String
}

struct LLMClient: Sendable {
    var baseURL: URL
    var apiKey: String
    var model: String
    var session: URLSession
    var temperature: Double

    init(
        baseURLString: String,
        apiKey: String,
        model: String,
        session: URLSession = .shared,
        temperature: Double = 0.4
    ) throws {
        let trimmed = baseURLString.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let url = Self.normalizedBaseURL(from: trimmed) else {
            throw AppError.invalidAPIURL
        }
        self.baseURL = url
        self.apiKey = apiKey
        self.model = model.trimmingCharacters(in: .whitespacesAndNewlines)
        self.session = session
        self.temperature = temperature
    }

    func complete(messages: [LLMMessage]) async throws -> String {
        let endpoint = baseURL.appending(path: "chat/completions")
        var request = URLRequest(url: endpoint)
        request.httpMethod = "POST"
        request.timeoutInterval = 60
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(apiKey)", forHTTPHeaderField: "Authorization")

        let body = ChatCompletionRequest(
            model: model,
            messages: messages.map { .init(role: $0.role.rawValue, content: $0.content) },
            temperature: temperature
        )
        request.httpBody = try JSONEncoder().encode(body)

        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw AppError.network(error.localizedDescription)
        }

        guard let http = response as? HTTPURLResponse else {
            throw AppError.network("No HTTP response from the API.")
        }

        if http.statusCode == 401 || http.statusCode == 403 {
            throw AppError.server(http.statusCode, "Check your API key and that it is allowed for this endpoint.")
        }

        if !(200...299).contains(http.statusCode) {
            let apiMessage = (try? JSONDecoder().decode(APIErrorEnvelope.self, from: data))?.error?.message
            throw AppError.server(http.statusCode, apiMessage ?? fallbackErrorText(from: data))
        }

        let decoded: ChatCompletionResponse
        do {
            decoded = try JSONDecoder().decode(ChatCompletionResponse.self, from: data)
        } catch {
            throw AppError.decoding
        }

        let text = decoded.choices.first?.message.content?
            .trimmingCharacters(in: .whitespacesAndNewlines)
        guard let text, !text.isEmpty else { throw AppError.emptyTranslation }
        return stripWrappingQuotes(text)
    }

    func ping() async throws -> String {
        try await complete(messages: [
            LLMMessage(role: .user, content: "Reply with the single word pong and nothing else.")
        ])
    }

    private func fallbackErrorText(from data: Data) -> String {
        if let text = String(data: data, encoding: .utf8), !text.isEmpty {
            return String(text.prefix(240))
        }
        return "Unexpected API error."
    }

    private func stripWrappingQuotes(_ text: String) -> String {
        var result = text
        if result.hasPrefix("\"") && result.hasSuffix("\"") && result.count > 1 {
            result.removeFirst()
            result.removeLast()
        }
        return result.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    static func normalizedBaseURL(from raw: String) -> URL? {
        var value = raw
        if value.hasSuffix("/") { value.removeLast() }
        if value.hasSuffix("/chat/completions") {
            value = String(value.dropLast("/chat/completions".count))
        }
        if value.hasSuffix("/") { value.removeLast() }
        if !value.contains("://") {
            value = "https://" + value
        }
        if let url = URL(string: value),
           let host = url.host?.lowercased(),
           host == "api.openai.com" || host.hasSuffix(".openai.com"),
           !url.path.contains("v1") {
            value += "/v1"
        }
        return URL(string: value)
    }
}

private struct ChatCompletionRequest: Encodable {
    struct Message: Encodable {
        var role: String
        var content: String
    }

    var model: String
    var messages: [Message]
    var temperature: Double
}

private struct ChatCompletionResponse: Decodable {
    struct Choice: Decodable {
        struct Message: Decodable {
            var content: String?
        }
        var message: Message
    }
    var choices: [Choice]
}

private struct APIErrorEnvelope: Decodable {
    struct APIError: Decodable {
        var message: String?
    }
    var error: APIError?
}
