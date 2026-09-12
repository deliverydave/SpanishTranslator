import Foundation
import UIKit
import Vision

enum OCRService {
    static func recognizeText(in image: UIImage) async throws -> String {
        let prepared = preparedImage(image)
        guard let cgImage = prepared.cgImage else { throw AppError.ocrFailed }

        return try await withCheckedThrowingContinuation { continuation in
            let request = VNRecognizeTextRequest { request, error in
                if let error {
                    continuation.resume(throwing: AppError.network(error.localizedDescription))
                    return
                }

                let observations = (request.results as? [VNRecognizedTextObservation]) ?? []
                let lines = observations.compactMap { observation in
                    observation.topCandidates(1).first?.string
                }
                .map { $0.trimmingCharacters(in: .whitespacesAndNewlines) }
                .filter { !$0.isEmpty }

                let joined = joinedMessageLines(lines)
                if joined.isEmpty {
                    continuation.resume(throwing: AppError.noTextInImage)
                } else {
                    continuation.resume(returning: joined)
                }
            }

            request.recognitionLevel = .accurate
            request.usesLanguageCorrection = true
            request.automaticallyDetectsLanguage = true
            request.recognitionLanguages = ["es-ES", "es-MX", "es-US", "en-US"]
            request.minimumTextHeight = 0.015

            let handler = VNImageRequestHandler(
                cgImage: cgImage,
                orientation: CGImagePropertyOrientation(prepared.imageOrientation),
                options: [:]
            )
            DispatchQueue.global(qos: .userInitiated).async {
                do {
                    try handler.perform([request])
                } catch {
                    continuation.resume(throwing: AppError.ocrFailed)
                }
            }
        }
    }

    /// Prefer a reasonably sized image so Vision has enough pixels without huge memory use.
    private static func preparedImage(_ image: UIImage) -> UIImage {
        let maxDimension: CGFloat = 2048
        let size = image.size
        let longest = max(size.width, size.height)
        guard longest > maxDimension, longest > 0 else { return image }

        let scale = maxDimension / longest
        let newSize = CGSize(width: size.width * scale, height: size.height * scale)
        let renderer = UIGraphicsImageRenderer(size: newSize)
        return renderer.image { _ in
            image.draw(in: CGRect(origin: .zero, size: newSize))
        }
    }

    /// Drop common iMessage chrome that OCR often picks up around a bubble.
    private static func joinedMessageLines(_ lines: [String]) -> String {
        let ignored = [
            "imessage", "text message", "delivered", "read", "today", "yesterday",
            "iMessage", "SMS", "Tapback", "Edited"
        ]
        let cleaned = lines.filter { line in
            let lower = line.lowercased()
            if ignored.contains(where: { lower == $0.lowercased() }) { return false }
            if lower.hasPrefix("delivered") || lower.hasPrefix("read ") { return false }
            if line.count <= 2 && line.allSatisfy(\.isNumber) { return false }
            return true
        }
        return cleaned.joined(separator: "\n")
            .trimmingCharacters(in: .whitespacesAndNewlines)
    }
}

private extension CGImagePropertyOrientation {
    init(_ orientation: UIImage.Orientation) {
        switch orientation {
        case .up: self = .up
        case .down: self = .down
        case .left: self = .left
        case .right: self = .right
        case .upMirrored: self = .upMirrored
        case .downMirrored: self = .downMirrored
        case .leftMirrored: self = .leftMirrored
        case .rightMirrored: self = .rightMirrored
        @unknown default: self = .up
        }
    }
}
