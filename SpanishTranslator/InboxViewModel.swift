import Foundation
import UIKit
import Combine

@MainActor
final class InboxViewModel: ObservableObject {
    @Published var spanishText = ""
    @Published var englishText = ""
    @Published var isReadingImage = false
    @Published var isTranslating = false
    @Published var errorMessage: String?
    @Published var sourceLabel: String?

    var canTranslate: Bool {
        !spanishText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !isReadingImage && !isTranslating
    }

    func pasteText() {
        guard let text = PasteboardReader.text() else {
            errorMessage = "Nothing to paste. Copy Luis’s Spanish text first."
            return
        }
        spanishText = text
        sourceLabel = "Pasted text"
        englishText = ""
        errorMessage = nil
    }

    func pasteImage() async {
        guard let image = PasteboardReader.image() else {
            errorMessage = "No screenshot on the clipboard. Copy the Messages bubble image, then try again."
            return
        }
        await recognize(image, source: "Pasted screenshot")
    }

    func recognize(_ image: UIImage, source: String) async {
        isReadingImage = true
        errorMessage = nil
        sourceLabel = source
        defer { isReadingImage = false }

        do {
            let text = try await OCRService.recognizeText(in: image)
            spanishText = text
            englishText = ""
        } catch {
            errorMessage = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
        }
    }

    func translate(using api: APIConfiguration, apple: AppleTranslationBridge) async {
        let spanish = spanishText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !spanish.isEmpty else {
            errorMessage = AppError.emptyInput.localizedDescription
            return
        }

        isTranslating = true
        errorMessage = nil
        defer { isTranslating = false }

        do {
            englishText = try await ComposeViewModel.translate(
                spanish,
                direction: .spanishToEnglish,
                api: api,
                apple: apple
            )
        } catch {
            errorMessage = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
        }
    }

    func clear() {
        spanishText = ""
        englishText = ""
        sourceLabel = nil
        errorMessage = nil
    }
}
