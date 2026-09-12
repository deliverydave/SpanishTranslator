import Foundation
import Combine
import Contacts

@MainActor
final class ContactStore: ObservableObject {
    private static let defaultsKey = "defaultLuisContact"

    @Published var contact: LuisContact? {
        didSet { persist() }
    }

    init() {
        contact = Self.load()
    }

    func apply(contact: CNContact, preferredPhone: String? = nil) {
        let name = CNContactFormatter.string(from: contact, style: .fullName)
            ?? [contact.givenName, contact.familyName]
                .filter { !$0.isEmpty }
                .joined(separator: " ")

        let phone = preferredPhone
            ?? Self.bestPhone(from: contact)
            ?? ""

        self.contact = LuisContact(
            identifier: contact.identifier,
            displayName: name.isEmpty ? "Luis" : name,
            phoneNumber: Self.normalizedPhone(phone)
        )
    }

    func applyManual(name: String, phone: String) {
        let trimmedName = name.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedPhone = Self.normalizedPhone(phone)
        contact = LuisContact(
            identifier: contact?.identifier ?? "manual",
            displayName: trimmedName.isEmpty ? "Luis" : trimmedName,
            phoneNumber: trimmedPhone
        )
    }

    func clear() {
        contact = nil
    }

    var displayLabel: String {
        contact?.shortLabel ?? "No default contact yet"
    }

    static func bestPhone(from contact: CNContact) -> String? {
        let numbers = contact.phoneNumbers
        let preferredLabels: [String] = [
            CNLabelPhoneNumberiPhone,
            CNLabelPhoneNumberMobile,
            CNLabelPhoneNumberMain
        ]

        for label in preferredLabels {
            if let match = numbers.first(where: { $0.label == label }) {
                return match.value.stringValue
            }
        }
        return numbers.first?.value.stringValue
    }

    static func normalizedPhone(_ raw: String) -> String {
        raw.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private func persist() {
        guard let contact else {
            UserDefaults.standard.removeObject(forKey: Self.defaultsKey)
            return
        }
        if let data = try? JSONEncoder().encode(contact) {
            UserDefaults.standard.set(data, forKey: Self.defaultsKey)
        }
    }

    private static func load() -> LuisContact? {
        guard let data = UserDefaults.standard.data(forKey: defaultsKey) else { return nil }
        return try? JSONDecoder().decode(LuisContact.self, from: data)
    }
}
