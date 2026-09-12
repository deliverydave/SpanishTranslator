import SwiftUI

struct SettingsView: View {
    @EnvironmentObject private var api: APIConfiguration
    @EnvironmentObject private var contacts: ContactStore

    @State private var apiKeyDraft = ""
    @State private var showKey = false
    @State private var showContactPicker = false
    @State private var status: String?
    @State private var statusIsError = false
    @State private var isTesting = false
    @State private var manualName: String = ""
    @State private var manualPhone: String = ""

    var body: some View {
        NavigationStack {
            Form {
                contactSection
                apiSection
                aboutSection
            }
            .navigationTitle("Settings")
            .onAppear {
                manualName = contacts.contact?.displayName ?? "Luis"
                manualPhone = contacts.contact?.phoneNumber ?? ""
                if api.hasAPIKey, apiKeyDraft.isEmpty {
                    apiKeyDraft = "••••••••"
                }
            }
            .sheet(isPresented: $showContactPicker) {
                ContactPickerRepresentable(
                    onSelect: { contact in
                        contacts.apply(contact: contact)
                        manualName = contacts.contact?.displayName ?? "Luis"
                        manualPhone = contacts.contact?.phoneNumber ?? ""
                        showContactPicker = false
                    },
                    onCancel: { showContactPicker = false }
                )
                .ignoresSafeArea()
            }
        }
    }

    private var contactSection: some View {
        Section {
            LabeledContent("Name") {
                Text(contacts.contact?.displayName ?? "Not set")
            }
            LabeledContent("Phone") {
                Text(contacts.contact?.phoneNumber ?? "Not set")
                    .textSelection(.enabled)
            }

            Button {
                showContactPicker = true
            } label: {
                Label("Choose from Contacts", systemImage: "person.crop.circle.badge.plus")
            }

            TextField("Display name", text: $manualName)
                .textContentType(.name)
            TextField("Phone number", text: $manualPhone)
                .keyboardType(.phonePad)
                .textContentType(.telephoneNumber)

            Button("Save name & number") {
                contacts.applyManual(name: manualName, phone: manualPhone)
                status = "Saved \(contacts.displayLabel) as the default recipient."
                statusIsError = false
            }
            .disabled(manualPhone.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)

            if contacts.contact != nil {
                Button("Clear default contact", role: .destructive) {
                    contacts.clear()
                    manualName = "Luis"
                    manualPhone = ""
                }
            }
        } header: {
            Text("Default contact")
        } footer: {
            Text("Pick Luis once. SpanishTranslator keeps that name and number on this device and opens Messages to them. You can change it anytime. Contacts are only used to choose this recipient.")
        }
    }

    private var apiSection: some View {
        Section {
            Group {
                if showKey {
                    TextField("API key", text: $apiKeyDraft)
                } else {
                    SecureField("API key (stored in Keychain)", text: $apiKeyDraft)
                }
            }
            .textContentType(.password)
            .autocorrectionDisabled()
            .textInputAutocapitalization(.never)

            Toggle("Show key while editing", isOn: $showKey)

            TextField("Base URL", text: $api.baseURL)
                .keyboardType(.URL)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()

            TextField("Model", text: $api.model)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()

            Button("Save API key") {
                saveKey()
            }

            if api.hasAPIKey {
                Button("Remove API key", role: .destructive) {
                    api.clearAPIKey()
                    apiKeyDraft = ""
                    status = "API key removed from Keychain."
                    statusIsError = false
                }
            }

            Button {
                Task { await testConnection() }
            } label: {
                if isTesting {
                    Label("Testing…", systemImage: "ellipsis")
                } else {
                    Label("Test connection", systemImage: "bolt.horizontal")
                }
            }
            .disabled(isTesting)

            if let status {
                Text(status)
                    .font(.footnote)
                    .foregroundStyle(statusIsError ? Color.red : AppTheme.teal)
            }
        } header: {
            Text("Language model")
        } footer: {
            Text("Compose and polish always use this OpenAI-compatible API (Chat Completions). Translation uses it when a key is saved; on iOS 18+ without a key, English ↔ Spanish can fall back to Apple Translation. Default: \(APIConfiguration.defaultBaseURL) / \(APIConfiguration.defaultModel). OpenRouter and other compatible hosts work if you set the base URL to their /v1 endpoint.")
        }
    }

    private var aboutSection: some View {
        Section("How this app works") {
            Text("1. Compose a text in English, then refine it in the chat until it sounds right.")
            Text("2. Translate to natural Spanish. Edit if you want, then open Messages — you tap Send.")
            Text("3. When Luis replies, paste the Spanish or a screenshot. Vision reads the bubble on-device.")
            Text("The app cannot auto-send SMS or read your Messages inbox. That is an Apple restriction, not a missing feature.")
        }
    }

    private func saveKey() {
        let value = apiKeyDraft.trimmingCharacters(in: .whitespacesAndNewlines)
        if value.hasPrefix("••") {
            status = "Key unchanged. Paste a new key to replace it."
            statusIsError = false
            return
        }
        do {
            try api.saveAPIKey(value)
            apiKeyDraft = value.isEmpty ? "" : "••••••••"
            status = value.isEmpty ? "API key cleared." : "API key saved in Keychain."
            statusIsError = false
        } catch {
            status = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
            statusIsError = true
        }
    }

    private func testConnection() async {
        isTesting = true
        defer { isTesting = false }
        do {
            if apiKeyDraft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty == false,
               apiKeyDraft.hasPrefix("••") == false {
                try api.saveAPIKey(apiKeyDraft)
            }
            let reply = try await api.makeClient().ping()
            status = "Connected. Model replied: \(reply)"
            statusIsError = false
        } catch {
            status = (error as? LocalizedError)?.errorDescription ?? error.localizedDescription
            statusIsError = true
        }
    }
}

#Preview {
    SettingsView()
        .environmentObject(APIConfiguration())
        .environmentObject(ContactStore())
}
