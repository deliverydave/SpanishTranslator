import SwiftUI
import MessageUI

struct SpanishSendSheet: View {
    let english: String
    @Binding var spanish: String

    @EnvironmentObject private var contacts: ContactStore
    @Environment(\.dismiss) private var dismiss
    @Environment(\.openURL) private var openURL

    @State private var showComposer = false
    @State private var showContactPicker = false
    @State private var banner: String?
    @State private var copied = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    labeledBlock(title: "English", text: english)

                    VStack(alignment: .leading, spacing: 8) {
                        Text("Spanish (editable)")
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(AppTheme.teal)
                        TextEditor(text: $spanish)
                            .frame(minHeight: 140)
                            .padding(8)
                            .scrollContentBackground(.hidden)
                            .background(
                                RoundedRectangle(cornerRadius: 12, style: .continuous)
                                    .fill(Color(.secondarySystemBackground))
                            )
                    }

                    contactRow

                    if let banner {
                        Banner(text: banner, style: .info)
                    }

                    Button {
                        sendTapped()
                    } label: {
                        Label("Open Messages", systemImage: "message.fill")
                    }
                    .buttonStyle(PrimaryButtonStyle(enabled: canSend))
                    .disabled(!canSend)

                    Button {
                        PasteboardReader.copy(spanish)
                        copied = true
                        banner = "Spanish copied. Paste it into Messages if the composer isn’t available."
                    } label: {
                        Label(copied ? "Copied" : "Copy Spanish", systemImage: "doc.on.doc")
                    }
                    .buttonStyle(SecondaryButtonStyle())

                    Text("Apple does not let apps send SMS silently. Messages opens with Luis and this Spanish text filled in — you tap Send.")
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                }
                .padding()
            }
            .navigationTitle("Send to Luis")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Close") { dismiss() }
                }
            }
            .sheet(isPresented: $showComposer) {
                MessageComposeRepresentable(
                    recipients: [contacts.contact?.phoneNumber ?? ""],
                    body: spanish
                ) { result in
                    showComposer = false
                    switch result {
                    case .sent:
                        banner = "Messages reported the text as sent."
                    case .cancelled:
                        banner = "Composer closed without sending."
                    case .failed:
                        banner = "Messages could not send. The Spanish text is still here to copy."
                    @unknown default:
                        break
                    }
                }
            }
            .sheet(isPresented: $showContactPicker) {
                ContactPickerRepresentable(
                    onSelect: { contact in
                        contacts.apply(contact: contact)
                        showContactPicker = false
                    },
                    onCancel: { showContactPicker = false }
                )
                .ignoresSafeArea()
            }
        }
    }

    private var canSend: Bool {
        !spanish.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        && (contacts.contact?.isSendable == true)
    }

    private var contactRow: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Recipient")
                .font(.caption.weight(.semibold))
                .foregroundStyle(.secondary)
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text(contacts.contact?.displayName ?? "No contact selected")
                        .font(.body.weight(.semibold))
                    Text(contacts.contact?.phoneNumber ?? "Pick Luis from Contacts")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
                Spacer()
                Button(contacts.contact == nil ? "Choose" : "Change") {
                    showContactPicker = true
                }
                .font(.subheadline.weight(.semibold))
            }
            .padding(12)
            .background(
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .fill(Color(.secondarySystemBackground))
            )
        }
    }

    private func labeledBlock(title: String, text: String) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title)
                .font(.caption.weight(.semibold))
                .foregroundStyle(.secondary)
            Text(text)
                .frame(maxWidth: .infinity, alignment: .leading)
                .textSelection(.enabled)
        }
        .padding(12)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(Color(.secondarySystemBackground))
        )
    }

    private func sendTapped() {
        banner = nil
        guard contacts.contact?.isSendable == true else {
            banner = AppError.noContact.localizedDescription
            showContactPicker = true
            return
        }
        if MessageComposer.canSendText {
            showComposer = true
            return
        }

        PasteboardReader.copy(spanish)
        if let url = MessageComposer.smsURL(phone: contacts.contact!.phoneNumber, body: spanish) {
            openURL(url)
        }
        banner = AppError.messagesUnavailable.localizedDescription
    }
}
