import Foundation
import Combine

@MainActor
final class ComposeViewModel: ObservableObject {
    private static let persistKey = "compose.snapshot"
    static let systemPrompt = """
    You are a writing assistant for SMS texts to Luis, a Spanish-speaking contact. The user drafts in English.

    Your job: produce a single polished English text message they can send. It will be translated to Spanish later.

    Rules:
    - Reply with ONLY the full current English message. No quotes, no preamble, no alternatives, no commentary.
    - Incorporate the user's latest notes or edits into a complete message (not a delta or a list of changes).
    - Keep the tone friendly, natural, and concise — like a real text, not an email.
    - Do not invent facts, times, places, or commitments the user did not mention.
    - If they add a greeting (for example "Good morning Luis"), include that greeting.
    - If they change wording (for example "don't say 25%, say seems like rain"), rewrite the whole message with that change.
    """

    @Published var messages: [ChatTurn] = []
    @Published var input = ""
    @Published var currentDraft = ""
    @Published var isWorking = false
    @Published var isTranslating = false
    @Published var errorMessage: String?
    @Published var showSpanishSheet = false
    @Published var spanishText = ""

    init() {
        restore()
    }

    var canSend: Bool {
        !input.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !isWorking
    }

    var canTranslate: Bool {
        !currentDraft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !isWorking && !isTranslating
    }

    func sendNote(using api: APIConfiguration) async {
        let note = input.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !note.isEmpty else {
            errorMessage = AppError.emptyInput.localizedDescription
            return
        }
        guard !isWorking else { return }

        errorMessage = nil
        isWorking = true
        input = ""
        messages.append(ChatTurn(role: .user, text: note))
        persist()

        do {
            let client = try api.makeClient()
            var llmMessages = [LLMMessage(role: .system, content: Self.systemPrompt)]
            for turn in messages {
                llmMessages.append(
                    LLMMessage(role: turn.role == .user ? .user : .assistant, content: turn.text)
                )
            }
            let polished = try await client.complete(messages: llmMessages)
            messages.append(ChatTurn(role: .assistant, text: polished))
            currentDraft = polished
            persist()
        } catch {
            errorMessage = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
        }

        isWorking = false
    }

    func useInputAsDraft() {
        let text = input.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        currentDraft = text
        input = ""
        persist()
    }

    func adoptDraft(_ text: String) {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        currentDraft = trimmed
        persist()
    }

    func translateDraft(using api: APIConfiguration, apple: AppleTranslationBridge) async {
        let english = currentDraft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !english.isEmpty else {
            errorMessage = AppError.emptyDraft.localizedDescription
            return
        }

        errorMessage = nil
        isTranslating = true
        defer { isTranslating = false }

        do {
            let spanish = try await Self.translate(english, direction: .englishToSpanish, api: api, apple: apple)
            spanishText = spanish
            showSpanishSheet = true
        } catch {
            errorMessage = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
        }
    }

    func startNewDraft() {
        messages = []
        currentDraft = ""
        input = ""
        spanishText = ""
        errorMessage = nil
        showSpanishSheet = false
        persist()
    }

    func persist() {
        let snapshot = ComposeSnapshot(messages: messages, currentDraft: currentDraft)
        if let data = try? JSONEncoder().encode(snapshot) {
            UserDefaults.standard.set(data, forKey: Self.persistKey)
        }
    }

    private func restore() {
        guard let data = UserDefaults.standard.data(forKey: Self.persistKey),
              let snapshot = try? JSONDecoder().decode(ComposeSnapshot.self, from: data)
        else { return }
        messages = snapshot.messages
        currentDraft = snapshot.currentDraft
    }

    static func translate(
        _ text: String,
        direction: TranslationDirection,
        api: APIConfiguration,
        apple: AppleTranslationBridge
    ) async throws -> String {
        if api.hasAPIKey {
            return try await TranslationEngine(client: api.makeClient()).translate(text, direction: direction)
        }
        return try await apple.translate(text, direction: direction)
    }
}
