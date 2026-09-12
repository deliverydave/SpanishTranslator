import Foundation
import SwiftUI
#if canImport(Translation)
import Translation
#endif

enum TranslationDirection: Equatable {
    case englishToSpanish
    case spanishToEnglish

    var systemPrompt: String {
        switch self {
        case .englishToSpanish:
            return """
            Translate the following English SMS into natural, conversational Spanish.
            Use familiar tú unless the English is clearly more formal.
            Latin American Spanish is preferred.
            Reply with ONLY the Spanish text — no quotes, labels, or notes.
            Preserve names, numbers, dates, and meaning.
            Sound like a real text message, not a word-for-word translation.
            """
        case .spanishToEnglish:
            return """
            Translate the following Spanish SMS into clear, natural English.
            Reply with ONLY the English text — no quotes, labels, or notes.
            Preserve names, numbers, dates, and meaning.
            Sound like a real text message someone would actually send.
            """
        }
    }
}

struct TranslationEngine: Sendable {
    var client: LLMClient?

    func translate(_ text: String, direction: TranslationDirection) async throws -> String {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { throw AppError.emptyInput }

        if let client {
            return try await client.complete(messages: [
                LLMMessage(role: .system, content: direction.systemPrompt),
                LLMMessage(role: .user, content: trimmed)
            ])
        }

        throw AppError.missingAPIKey
    }
}

#if canImport(Translation)
/// Hidden helper that runs Apple's on-device Translation framework (iOS 18+).
@available(iOS 18.0, *)
struct AppleTranslationTask: View {
    let text: String
    let sourceIdentifier: String
    let targetIdentifier: String
    let onResult: (Result<String, Error>) -> Void

    @State private var configuration: TranslationSession.Configuration?

    var body: some View {
        Color.clear
            .frame(width: 1, height: 1)
            .accessibilityHidden(true)
            .translationTask(configuration) { session in
                do {
                    let response = try await session.translate(text)
                    let output = response.targetText.trimmingCharacters(in: .whitespacesAndNewlines)
                    if output.isEmpty {
                        onResult(.failure(AppError.emptyTranslation))
                    } else {
                        onResult(.success(output))
                    }
                } catch {
                    onResult(.failure(error))
                }
            }
            .onAppear {
                configuration = TranslationSession.Configuration(
                    source: Locale.Language(identifier: sourceIdentifier),
                    target: Locale.Language(identifier: targetIdentifier)
                )
            }
    }
}
#endif

@MainActor
final class AppleTranslationBridge: ObservableObject {
    @Published var request: Request?

    struct Request: Equatable {
        let id = UUID()
        let text: String
        let sourceIdentifier: String
        let targetIdentifier: String
    }

    func translate(_ text: String, direction: TranslationDirection) async throws -> String {
        if #unavailable(iOS 18.0) {
            throw AppError.appleTranslationUnavailable
        }

        let source: String
        let target: String
        switch direction {
        case .englishToSpanish:
            source = "en"
            target = "es"
        case .spanishToEnglish:
            source = "es"
            target = "en"
        }

        return try await withCheckedThrowingContinuation { continuation in
            var settled = false
            let finish: (Result<String, Error>) -> Void = { result in
                guard !settled else { return }
                settled = true
                continuation.resume(with: result)
            }

            AppleTranslationBridge.pendingFinish = finish
            request = Request(text: text, sourceIdentifier: source, targetIdentifier: target)

            // Safety timeout if the translation task never fires (older OS / missing languages).
            Task {
                try? await Task.sleep(for: .seconds(20))
                finish(.failure(AppError.appleTranslationUnavailable))
            }
        }
    }

    fileprivate static var pendingFinish: ((Result<String, Error>) -> Void)?
}

struct AppleTranslationHost: ViewModifier {
    @ObservedObject var bridge: AppleTranslationBridge

    func body(content: Content) -> some View {
        content.background {
            if let request = bridge.request {
                #if canImport(Translation)
                if #available(iOS 18.0, *) {
                    AppleTranslationTask(
                        text: request.text,
                        sourceIdentifier: request.sourceIdentifier,
                        targetIdentifier: request.targetIdentifier
                    ) { result in
                        Task { @MainActor in
                            AppleTranslationBridge.pendingFinish?(result)
                            AppleTranslationBridge.pendingFinish = nil
                            bridge.request = nil
                        }
                    }
                }
                #endif
            }
        }
    }
}

extension View {
    func appleTranslationHost(_ bridge: AppleTranslationBridge) -> some View {
        modifier(AppleTranslationHost(bridge: bridge))
    }
}
