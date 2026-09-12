import SwiftUI

struct ComposeView: View {
    @EnvironmentObject private var api: APIConfiguration
    @EnvironmentObject private var contacts: ContactStore
    @EnvironmentObject private var appleTranslation: AppleTranslationBridge
    @StateObject private var model = ComposeViewModel()
    @FocusState private var inputFocused: Bool

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                contactHeader
                Divider()
                currentDraftCard
                chatList
                if let error = model.errorMessage {
                    Banner(text: error)
                        .padding(.horizontal)
                        .padding(.top, 8)
                }
                composerBar
            }
            .background(Color(.systemGroupedBackground))
            .navigationTitle("Compose")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarLeading) {
                    Button("New") { model.startNewDraft() }
                        .disabled(model.messages.isEmpty && model.currentDraft.isEmpty)
                }
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        Task { await model.translateDraft(using: api, apple: appleTranslation) }
                    } label: {
                        if model.isTranslating {
                            ProgressView()
                        } else {
                            Text("Translate")
                                .fontWeight(.semibold)
                        }
                    }
                    .disabled(!model.canTranslate)
                }
            }
            .sheet(isPresented: $model.showSpanishSheet) {
                SpanishSendSheet(
                    english: model.currentDraft,
                    spanish: $model.spanishText
                )
            }
        }
    }

    private var contactHeader: some View {
        HStack(spacing: 12) {
            Image(systemName: "person.crop.circle.fill")
                .font(.title2)
                .foregroundStyle(AppTheme.teal)

            VStack(alignment: .leading, spacing: 2) {
                Text(contacts.contact?.displayName ?? "Luis")
                    .font(.subheadline.weight(.semibold))
                Text(contacts.contact?.phoneNumber.isEmpty == false
                     ? contacts.contact!.phoneNumber
                     : "Pick a default contact in Settings")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }

            Spacer()

            if !api.hasAPIKey {
                Text("API key needed")
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(AppTheme.coral)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(AppTheme.coral.opacity(0.12), in: Capsule())
            }
        }
        .padding(.horizontal)
        .padding(.vertical, 10)
        .background(Color(.systemBackground))
    }

    private var currentDraftCard: some View {
        Group {
            if !model.currentDraft.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    HStack {
                        Text("English draft")
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(AppTheme.teal)
                        Spacer()
                        Button("Copy") {
                            PasteboardReader.copy(model.currentDraft)
                        }
                        .font(.caption.weight(.semibold))
                    }
                    Text(model.currentDraft)
                        .font(.body)
                        .foregroundStyle(.primary)
                        .textSelection(.enabled)
                }
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .fill(AppTheme.sand)
                )
                .padding(.horizontal)
                .padding(.top, 12)
                .padding(.bottom, 4)
            }
        }
    }

    private var chatList: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 10) {
                    if model.messages.isEmpty {
                        emptyState
                    }
                    ForEach(model.messages) { turn in
                        ChatBubbleView(turn: turn)
                            .id(turn.id)
                    }
                    if model.isWorking {
                        HStack {
                            ProgressView()
                            Text("Polishing…")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        }
                        .padding(.horizontal, 4)
                        .id("working")
                    }
                }
                .padding()
            }
            .onChange(of: model.messages.count) { _, _ in
                scrollToBottom(proxy)
            }
            .onChange(of: model.isWorking) { _, _ in
                scrollToBottom(proxy)
            }
        }
    }

    private var emptyState: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Draft with Luis in English")
                .font(.title3.weight(.semibold))
            Text("Dump rough notes. The app will polish them into a text you actually want to send. Tweak until it sounds right, then translate to Spanish.")
                .font(.subheadline)
                .foregroundStyle(.secondary)
            VStack(alignment: .leading, spacing: 6) {
                exampleRow("You", "25% chance of rain tomorrow. How about starting Monday?")
                exampleRow("App", "Hey Luis — about a 25% chance of rain tomorrow. Want to start Monday instead?")
                exampleRow("You", "Good morning Luis. Don’t say 25%, say seems like rain.")
            }
            .padding(.top, 4)
        }
        .padding(4)
    }

    private func exampleRow(_ who: String, _ text: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(who)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(.secondary)
            Text(text)
                .font(.footnote)
        }
    }

    private var composerBar: some View {
        VStack(spacing: 8) {
            if !api.hasAPIKey {
                Banner(
                    text: "Add an API key in Settings to polish drafts. Translation can still use Apple Translation on iOS 18+.",
                    style: .info
                )
            }

            HStack(alignment: .bottom, spacing: 8) {
                TextField("Rough notes or tweaks…", text: $model.input, axis: .vertical)
                    .textFieldStyle(.plain)
                    .lineLimit(1...6)
                    .focused($inputFocused)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                    .background(Color(.systemBackground), in: RoundedRectangle(cornerRadius: 14, style: .continuous))

                Button {
                    Task { await model.sendNote(using: api) }
                } label: {
                    Image(systemName: "arrow.up.circle.fill")
                        .font(.system(size: 32))
                        .foregroundStyle(model.canSend && api.hasAPIKey ? AppTheme.teal : Color.gray.opacity(0.4))
                }
                .disabled(!model.canSend || !api.hasAPIKey)
                .accessibilityLabel("Polish draft")
            }

            HStack {
                Button("Use as draft") {
                    model.useInputAsDraft()
                }
                .font(.caption.weight(.semibold))
                .disabled(model.input.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)

                Spacer()

                Button {
                    Task { await model.translateDraft(using: api, apple: appleTranslation) }
                } label: {
                    Label(
                        model.isTranslating ? "Translating…" : "Translate to Spanish",
                        systemImage: "character.book.closed"
                    )
                    .font(.caption.weight(.semibold))
                }
                .disabled(!model.canTranslate)
            }
        }
        .padding(.horizontal)
        .padding(.top, 8)
        .padding(.bottom, 10)
        .background(.bar)
    }

    private func scrollToBottom(_ proxy: ScrollViewProxy) {
        if model.isWorking {
            withAnimation { proxy.scrollTo("working", anchor: .bottom) }
        } else if let last = model.messages.last {
            withAnimation { proxy.scrollTo(last.id, anchor: .bottom) }
        }
    }
}

struct ChatBubbleView: View {
    let turn: ChatTurn

    var body: some View {
        HStack {
            if turn.role == .user { Spacer(minLength: 40) }

            VStack(alignment: turn.role == .user ? .trailing : .leading, spacing: 4) {
                Text(turn.role == .user ? "You" : "Polished English")
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(.secondary)
                Text(turn.text)
                    .font(.body)
                    .foregroundStyle(turn.role == .user ? Color.white : Color.primary)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                    .background(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .fill(turn.role == .user ? AppTheme.teal : Color(.systemBackground))
                    )
                    .textSelection(.enabled)
            }

            if turn.role == .assistant { Spacer(minLength: 40) }
        }
    }
}

#Preview {
    ComposeView()
        .environmentObject(APIConfiguration())
        .environmentObject(ContactStore())
        .environmentObject(AppleTranslationBridge())
}
