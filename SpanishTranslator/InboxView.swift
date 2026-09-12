import SwiftUI

struct InboxView: View {
    @EnvironmentObject private var api: APIConfiguration
    @EnvironmentObject private var appleTranslation: AppleTranslationBridge
    @StateObject private var model = InboxViewModel()
    @State private var showPhotoPicker = false
    @State private var copiedEnglish = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    intro
                    actionRow
                    spanishEditor
                    translateButton
                    englishResult
                    if let error = model.errorMessage {
                        Banner(text: error)
                    }
                }
                .padding()
            }
            .background(Color(.systemGroupedBackground))
            .navigationTitle("Inbox")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Clear") { model.clear() }
                        .disabled(model.spanishText.isEmpty && model.englishText.isEmpty)
                }
            }
            .sheet(isPresented: $showPhotoPicker) {
                PhotoLibraryPicker(
                    onImage: { image in
                        showPhotoPicker = false
                        Task { await model.recognize(image, source: "Screenshot") }
                    },
                    onCancel: { showPhotoPicker = false }
                )
                .ignoresSafeArea()
            }
        }
    }

    private var intro: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Luis replied")
                .font(.title3.weight(.semibold))
            Text("Paste the Spanish text, or drop a screenshot of the Messages bubble. The app reads it on-device, shows the Spanish it found, then translates to English.")
                .font(.subheadline)
                .foregroundStyle(.secondary)
            if let source = model.sourceLabel {
                Text(source)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(AppTheme.teal)
            }
        }
    }

    private var actionRow: some View {
        VStack(spacing: 8) {
            HStack(spacing: 8) {
                Button {
                    model.pasteText()
                } label: {
                    Label("Paste text", systemImage: "doc.on.clipboard")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(SecondaryButtonStyle())

                Button {
                    Task { await model.pasteImage() }
                } label: {
                    Label("Paste image", systemImage: "photo.on.rectangle")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(SecondaryButtonStyle())
            }

            Button {
                showPhotoPicker = true
            } label: {
                Label("Choose screenshot", systemImage: "photo")
            }
            .buttonStyle(PrimaryButtonStyle())
        }
    }

    private var spanishEditor: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text("Spanish (editable)")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(AppTheme.teal)
                Spacer()
                if model.isReadingImage {
                    ProgressView()
                        .controlSize(.small)
                    Text("Reading screenshot…")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }
            TextEditor(text: $model.spanishText)
                .frame(minHeight: 140)
                .padding(8)
                .scrollContentBackground(.hidden)
                .background(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(Color(.systemBackground))
                )
        }
    }

    private var translateButton: some View {
        Button {
            Task { await model.translate(using: api, apple: appleTranslation) }
        } label: {
            if model.isTranslating {
                Label("Translating…", systemImage: "ellipsis")
            } else {
                Label("Translate to English", systemImage: "character.book.closed.fill")
            }
        }
        .buttonStyle(PrimaryButtonStyle(enabled: model.canTranslate))
        .disabled(!model.canTranslate)
    }

    @ViewBuilder
    private var englishResult: some View {
        if !model.englishText.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                HStack {
                    Text("English")
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(AppTheme.teal)
                    Spacer()
                    Button(copiedEnglish ? "Copied" : "Copy") {
                        PasteboardReader.copy(model.englishText)
                        copiedEnglish = true
                    }
                    .font(.caption.weight(.semibold))
                }
                Text(model.englishText)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .textSelection(.enabled)
                    .padding(12)
                    .background(
                        RoundedRectangle(cornerRadius: 12, style: .continuous)
                            .fill(AppTheme.sand)
                    )
            }
        }
    }
}

#Preview {
    InboxView()
        .environmentObject(APIConfiguration())
        .environmentObject(AppleTranslationBridge())
}
