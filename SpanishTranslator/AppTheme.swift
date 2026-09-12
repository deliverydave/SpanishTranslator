import SwiftUI

enum AppTheme {
    static let teal = Color(red: 0.08, green: 0.42, blue: 0.41)
    static let tealSoft = Color(red: 0.16, green: 0.55, blue: 0.53)
    static let sand = Color(red: 0.97, green: 0.94, blue: 0.89)
    static let ink = Color(red: 0.12, green: 0.16, blue: 0.18)
    static let coral = Color(red: 0.86, green: 0.42, blue: 0.32)
}

struct Banner: View {
    let text: String
    var style: Style = .error

    enum Style {
        case error
        case info
    }

    var body: some View {
        Text(text)
            .font(.footnote)
            .foregroundStyle(style == .error ? Color.red : Color.secondary)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(12)
            .background(
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .fill(style == .error ? Color.red.opacity(0.08) : Color.secondary.opacity(0.08))
            )
    }
}

struct PrimaryButtonStyle: ButtonStyle {
    var enabled: Bool = true

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .fill(enabled ? AppTheme.teal : Color.gray.opacity(0.45))
            )
            .opacity(configuration.isPressed ? 0.86 : 1)
    }
}

struct SecondaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.subheadline.weight(.semibold))
            .foregroundStyle(AppTheme.teal)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 12)
            .background(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .stroke(AppTheme.teal.opacity(0.35), lineWidth: 1)
                    .background(
                        RoundedRectangle(cornerRadius: 14, style: .continuous)
                            .fill(AppTheme.teal.opacity(0.06))
                    )
            )
            .opacity(configuration.isPressed ? 0.8 : 1)
    }
}
