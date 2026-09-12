import SwiftUI

@main
struct SpanishTranslatorApp: App {
    @StateObject private var api = APIConfiguration()
    @StateObject private var contacts = ContactStore()
    @StateObject private var appleTranslation = AppleTranslationBridge()

    var body: some Scene {
        WindowGroup {
            RootTabView()
                .environmentObject(api)
                .environmentObject(contacts)
                .environmentObject(appleTranslation)
                .appleTranslationHost(appleTranslation)
                .tint(AppTheme.teal)
        }
    }
}
