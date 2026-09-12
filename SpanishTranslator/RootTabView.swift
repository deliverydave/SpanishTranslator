import SwiftUI

struct RootTabView: View {
    @EnvironmentObject private var contacts: ContactStore
    @State private var selection = 0

    var body: some View {
        TabView(selection: $selection) {
            ComposeView()
                .tabItem {
                    Label("Compose", systemImage: "bubble.left.and.bubble.right.fill")
                }
                .tag(0)

            InboxView()
                .tabItem {
                    Label("Inbox", systemImage: "tray.and.arrow.down.fill")
                }
                .tag(1)

            SettingsView()
                .tabItem {
                    Label("Settings", systemImage: "gearshape.fill")
                }
                .tag(2)
        }
    }
}

#Preview {
    RootTabView()
        .environmentObject(APIConfiguration())
        .environmentObject(ContactStore())
        .environmentObject(AppleTranslationBridge())
}
