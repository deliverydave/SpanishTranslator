import UIKit

enum PasteboardReader {
    static var hasText: Bool {
        guard let text = UIPasteboard.general.string else { return false }
        return !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    static var hasImage: Bool {
        UIPasteboard.general.hasImages || UIPasteboard.general.image != nil
    }

    static func text() -> String? {
        let value = UIPasteboard.general.string?
            .trimmingCharacters(in: .whitespacesAndNewlines)
        return (value?.isEmpty == false) ? value : nil
    }

    static func image() -> UIImage? {
        UIPasteboard.general.image
    }

    static func copy(_ text: String) {
        UIPasteboard.general.string = text
    }
}
