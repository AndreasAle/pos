import UIKit
import WebKit
import Network

enum Yoka {
    static let baseURL = URL(string: "https://yokapos.com")!
    static let green = UIColor(red: 22/255, green: 163/255, blue: 74/255, alpha: 1)
    static let greenDark = UIColor(red: 21/255, green: 128/255, blue: 61/255, alpha: 1)
    static let navy = UIColor(red: 11/255, green: 27/255, blue: 63/255, alpha: 1)
    static let surface = UIColor(red: 241/255, green: 245/255, blue: 249/255, alpha: 1)
    static let ink = UIColor(red: 15/255, green: 23/255, blue: 42/255, alpha: 1)
    static let slate = UIColor(red: 100/255, green: 116/255, blue: 139/255, alpha: 1)
    static let border = UIColor(red: 226/255, green: 232/255, blue: 240/255, alpha: 1)
    static let onboardedKey = "onboarded"
    static let signedInKey = "signed_in"
    static let lastKasirPathKey = "last_kasir_path"
    static let guidedAccessTipKey = "guided_access_tip_seen"
    static var userAgentSuffix: String {
        let version = Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "1.0"
        return "YokaPOSApp/\(version)"
    }
    static func url(_ path: String) -> URL { URL(string: path, relativeTo: baseURL)!.absoluteURL }
    static func isPaired(_ completion: @escaping (Bool) -> Void) {
        WKWebsiteDataStore.default().httpCookieStore.getAllCookies { cookies in
            completion(cookies.contains { $0.name == "pos_outlet" })
        }
    }
}

class SceneDelegate: UIResponder, UIWindowSceneDelegate {
    var window: UIWindow?
    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        guard let windowScene = scene as? UIWindowScene else { return }
        let window = UIWindow(windowScene: windowScene)
        window.tintColor = Yoka.green
        window.rootViewController = SplashViewController()
        window.makeKeyAndVisible()
        self.window = window
    }
    func sceneDidBecomeActive(_ scene: UIScene) {
        NotificationCenter.default.post(name: .yokaSceneDidBecomeActive, object: nil)
    }
    func sceneDidEnterBackground(_ scene: UIScene) {
        NotificationCenter.default.post(name: .yokaSceneDidEnterBackground, object: nil)
    }
}

extension Notification.Name {
    static let yokaSceneDidBecomeActive = Notification.Name("YokaSceneDidBecomeActive")
    static let yokaSceneDidEnterBackground = Notification.Name("YokaSceneDidEnterBackground")
}

class YokaViewController: UIViewController {
    override var prefersStatusBarHidden: Bool { true }
    override var supportedInterfaceOrientations: UIInterfaceOrientationMask {
        UIDevice.current.userInterfaceIdiom == .phone ? .allButUpsideDown : .landscape
    }
    override var preferredInterfaceOrientationForPresentation: UIInterfaceOrientation {
        UIDevice.current.userInterfaceIdiom == .phone ? .portrait : .landscapeRight
    }
    override func viewDidLoad() { super.viewDidLoad(); view.backgroundColor = Yoka.surface }
    func replaceRoot(_ controller: UIViewController) {
        guard let window = view.window else { return }
        UIView.transition(with: window, duration: 0.22, options: .transitionCrossDissolve) { window.rootViewController = controller }
    }
}

extension UIView {
    func pin(to parent: UIView, inset: UIEdgeInsets = .zero) {
        translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            leadingAnchor.constraint(equalTo: parent.leadingAnchor, constant: inset.left),
            trailingAnchor.constraint(equalTo: parent.trailingAnchor, constant: -inset.right),
            topAnchor.constraint(equalTo: parent.topAnchor, constant: inset.top),
            bottomAnchor.constraint(equalTo: parent.bottomAnchor, constant: -inset.bottom),
        ])
    }
}

func makeLabel(_ text: String, size: CGFloat, weight: UIFont.Weight = .regular, color: UIColor = Yoka.ink, alignment: NSTextAlignment = .left) -> UILabel {
    let value = UILabel(); value.text = text; value.font = .systemFont(ofSize: size, weight: weight)
    value.textColor = color; value.textAlignment = alignment; value.numberOfLines = 0; return value
}

func yokaButton(_ title: String, filled: Bool = true) -> UIButton {
    var config = UIButton.Configuration.filled(); config.title = title
    config.baseBackgroundColor = filled ? Yoka.green : .white; config.baseForegroundColor = filled ? .white : Yoka.greenDark
    config.background.strokeColor = filled ? .clear : Yoka.green; config.background.strokeWidth = filled ? 0 : 1
    config.background.cornerRadius = 12; config.contentInsets = NSDirectionalEdgeInsets(top: 13, leading: 18, bottom: 13, trailing: 18)
    let button = UIButton(configuration: config); button.titleLabel?.font = .systemFont(ofSize: 16, weight: .bold)
    button.heightAnchor.constraint(greaterThanOrEqualToConstant: 50).isActive = true; return button
}

func logoView(width: CGFloat = 190) -> UIImageView {
    let image = UIImageView(image: UIImage(named: "YokaLogo")); image.contentMode = .scaleAspectFit
    image.widthAnchor.constraint(equalToConstant: width).isActive = true; image.heightAnchor.constraint(equalToConstant: width * 0.28).isActive = true
    return image
}

final class SplashViewController: YokaViewController {
    override func viewDidLoad() {
        super.viewDidLoad()
        let stack = UIStackView(arrangedSubviews: [logoView(width: 240), makeLabel("Kasir modern untuk bisnis F&B", size: 15, color: Yoka.slate, alignment: .center)])
        stack.axis = .vertical; stack.spacing = 12; stack.alignment = .center; stack.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(stack); NSLayoutConstraint.activate([stack.centerXAnchor.constraint(equalTo: view.centerXAnchor), stack.centerYAnchor.constraint(equalTo: view.centerYAnchor)])
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.1) { [weak self] in self?.route() }
    }
    private func route() {
        guard UserDefaults.standard.bool(forKey: Yoka.onboardedKey) else { replaceRoot(OnboardingViewController()); return }
        Yoka.isPaired { [weak self] paired in DispatchQueue.main.async {
            guard let self else { return }
            if paired || UserDefaults.standard.bool(forKey: Yoka.signedInKey) {
                let saved = UserDefaults.standard.string(forKey: Yoka.lastKasirPathKey) ?? "/pos"
                self.replaceRoot(KasirWebViewController(path: paired ? "/kasir" : saved))
            }
            else { self.replaceRoot(WelcomeViewController()) }
        }}
    }
}

final class OnboardingViewController: YokaViewController, UIPageViewControllerDataSource, UIPageViewControllerDelegate {
    struct Slide { let symbol: String; let title: String; let body: String }
    private let slides = [
        Slide(symbol: "cart.fill", title: "Jualan jadi mudah", body: "Catat pesanan, terima tunai dan QRIS, lalu cetak struk dalam hitungan detik."),
        Slide(symbol: "shippingbox.fill", title: "Stok dan resep terkontrol", body: "Stok bahan berkurang otomatis di setiap penjualan, lengkap dengan peringatan stok menipis."),
        Slide(symbol: "fork.knife", title: "Dapur ikut tersambung", body: "Tiket dapur tercetak otomatis di printer kedua, jadi tidak ada pesanan yang terlewat."),
        Slide(symbol: "chart.bar.fill", title: "Laporan selalu satu angka", body: "Penjualan, shift kasir, dan saldo sama di tablet maupun di laptop, kapan pun dibuka."),
    ]
    private lazy var pages = slides.map { SlideViewController(slide: $0) }
    private let pager = UIPageViewController(transitionStyle: .scroll, navigationOrientation: .horizontal)
    private let dots = UIPageControl(); private let nextButton = yokaButton("Lanjut"); private var index = 0
    override func viewDidLoad() {
        super.viewDidLoad(); addChild(pager); view.addSubview(pager.view); pager.didMove(toParent: self)
        pager.dataSource = self; pager.delegate = self; pager.setViewControllers([pages[0]], direction: .forward, animated: false)
        dots.numberOfPages = slides.count; dots.currentPageIndicatorTintColor = Yoka.green; dots.pageIndicatorTintColor = Yoka.border
        let skip = UIButton(type: .system); skip.setTitle("Lewati", for: .normal); skip.setTitleColor(Yoka.slate, for: .normal)
        skip.addAction(UIAction { [weak self] _ in self?.finish() }, for: .touchUpInside)
        nextButton.addAction(UIAction { [weak self] _ in self?.advance() }, for: .touchUpInside)
        [pager.view, dots, skip, nextButton].forEach { $0.translatesAutoresizingMaskIntoConstraints = false }
        view.addSubview(dots); view.addSubview(skip); view.addSubview(nextButton)
        NSLayoutConstraint.activate([
            skip.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor, constant: 16), skip.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -28),
            pager.view.leadingAnchor.constraint(equalTo: view.leadingAnchor), pager.view.trailingAnchor.constraint(equalTo: view.trailingAnchor), pager.view.topAnchor.constraint(equalTo: view.topAnchor), pager.view.bottomAnchor.constraint(equalTo: dots.topAnchor, constant: -8),
            dots.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -24), dots.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            nextButton.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -32), nextButton.centerYAnchor.constraint(equalTo: dots.centerYAnchor), nextButton.widthAnchor.constraint(equalToConstant: 150),
        ])
    }
    private func advance() { guard index < pages.count - 1 else { finish(); return }; index += 1; pager.setViewControllers([pages[index]], direction: .forward, animated: true); update() }
    private func finish() { UserDefaults.standard.set(true, forKey: Yoka.onboardedKey); replaceRoot(WelcomeViewController()) }
    private func update() { dots.currentPage = index; nextButton.configuration?.title = index == pages.count - 1 ? "Mulai" : "Lanjut" }
    func pageViewController(_ p: UIPageViewController, viewControllerBefore v: UIViewController) -> UIViewController? { guard let i = pages.firstIndex(where: { $0 === v }), i > 0 else { return nil }; return pages[i - 1] }
    func pageViewController(_ p: UIPageViewController, viewControllerAfter v: UIViewController) -> UIViewController? { guard let i = pages.firstIndex(where: { $0 === v }), i < pages.count - 1 else { return nil }; return pages[i + 1] }
    func pageViewController(_ p: UIPageViewController, didFinishAnimating f: Bool, previousViewControllers: [UIViewController], transitionCompleted c: Bool) { guard c, let v = p.viewControllers?.first, let i = pages.firstIndex(where: { $0 === v }) else { return }; index = i; update() }
}

final class SlideViewController: UIViewController {
    init(slide: OnboardingViewController.Slide) {
        super.init(nibName: nil, bundle: nil); view.backgroundColor = Yoka.surface
        let icon = UIImageView(image: UIImage(systemName: slide.symbol)); icon.tintColor = Yoka.green; icon.contentMode = .scaleAspectFit; icon.heightAnchor.constraint(equalToConstant: 140).isActive = true
        let card = UIView(); card.backgroundColor = .white; card.layer.cornerRadius = 28; card.layer.borderColor = Yoka.border.cgColor; card.layer.borderWidth = 1
        let content = UIStackView(arrangedSubviews: [icon, makeLabel(slide.title, size: 28, weight: .bold, alignment: .center), makeLabel(slide.body, size: 16, color: Yoka.slate, alignment: .center)])
        content.axis = .vertical; content.spacing = 18; card.addSubview(content); content.pin(to: card, inset: UIEdgeInsets(top: 36, left: 54, bottom: 36, right: 54))
        view.addSubview(card); card.translatesAutoresizingMaskIntoConstraints = false
        let phone = UIDevice.current.userInterfaceIdiom == .phone
        NSLayoutConstraint.activate([card.centerXAnchor.constraint(equalTo: view.centerXAnchor), card.centerYAnchor.constraint(equalTo: view.centerYAnchor, constant: -12), card.widthAnchor.constraint(equalTo: view.widthAnchor, multiplier: phone ? 0.86 : 0.54), card.widthAnchor.constraint(lessThanOrEqualToConstant: 680)])
    }
    required init?(coder: NSCoder) { fatalError() }
}

final class WelcomeViewController: YokaViewController {
    override func viewDidLoad() {
        super.viewDidLoad(); UserDefaults.standard.set(true, forKey: Yoka.onboardedKey)
        let title = makeLabel("Selamat datang", size: 27, weight: .bold, alignment: .center)
        let subtitle = makeLabel("Mulai dengan akun bisnis, atau masuk sebagai kasir.", size: 15, color: Yoka.slate, alignment: .center)
        let register = yokaButton("Mulai Berjualan"), login = yokaButton("Sudah punya akun? Masuk", filled: false), pin = UIButton(type: .system)
        pin.setTitle("Masuk sebagai kasir dengan PIN", for: .normal); pin.setTitleColor(Yoka.slate, for: .normal); pin.titleLabel?.font = .systemFont(ofSize: 15, weight: .bold); pin.heightAnchor.constraint(equalToConstant: 44).isActive = true
        register.addAction(UIAction { [weak self] _ in let c = RegisterViewController(); c.modalPresentationStyle = .fullScreen; self?.present(c, animated: true) }, for: .touchUpInside)
        login.addAction(UIAction { [weak self] _ in self?.replaceRoot(KasirWebViewController(path: "/pos")) }, for: .touchUpInside)
        pin.addAction(UIAction { [weak self] _ in self?.replaceRoot(KasirWebViewController(path: "/kasir")) }, for: .touchUpInside)
        let footer = UIStackView(); footer.axis = .horizontal; footer.spacing = 8; footer.alignment = .center; footer.distribution = .fillEqually
        for item in [("Syarat Layanan", "/syarat"), ("Kebijakan Privasi", "/privasi"), ("Hapus Akun", "/hapus-akun")] {
            let button = UIButton(type: .system); button.setTitle(item.0, for: .normal); button.setTitleColor(Yoka.slate, for: .normal); button.titleLabel?.font = .systemFont(ofSize: 12); button.titleLabel?.adjustsFontSizeToFitWidth = true; button.titleLabel?.minimumScaleFactor = 0.72
            button.addAction(UIAction { [weak self] _ in let c = KasirWebViewController(path: item.1, modal: true); c.modalPresentationStyle = .fullScreen; self?.present(c, animated: true) }, for: .touchUpInside); footer.addArrangedSubview(button)
        }
        let stack = UIStackView(arrangedSubviews: [logoView(), title, subtitle, register, login, pin, footer]); stack.axis = .vertical; stack.spacing = 11; stack.setCustomSpacing(22, after: subtitle)
        let card = UIView(); card.backgroundColor = .white; card.layer.cornerRadius = 24; card.layer.borderWidth = 1; card.layer.borderColor = Yoka.border.cgColor
        card.layer.shadowColor = Yoka.navy.cgColor; card.layer.shadowOpacity = 0.08; card.layer.shadowRadius = 20; card.layer.shadowOffset = CGSize(width: 0, height: 8)
        card.addSubview(stack); stack.pin(to: card, inset: UIEdgeInsets(top: 30, left: 38, bottom: 24, right: 38)); view.addSubview(card); card.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([card.centerXAnchor.constraint(equalTo: view.centerXAnchor), card.centerYAnchor.constraint(equalTo: view.centerYAnchor), card.widthAnchor.constraint(equalToConstant: 520), card.widthAnchor.constraint(lessThanOrEqualTo: view.safeAreaLayoutGuide.widthAnchor, constant: -48)])
    }
}

final class RegisterViewController: YokaViewController {
    private let nameField = FieldView(title: "Nama lengkap", placeholder: "Nama pemilik bisnis", contentType: .name)
    private let phoneField = FieldView(title: "Nomor HP", placeholder: "812xxxxxxxx", prefix: "+62", keyboard: .phonePad, contentType: .telephoneNumber)
    private let emailField = FieldView(title: "Email", placeholder: "email@bisnis.com", keyboard: .emailAddress, contentType: .emailAddress)
    private let passwordField = FieldView(title: "Password", placeholder: "Minimal 8 karakter", secure: true, contentType: .newPassword)
    private let formError = makeLabel("", size: 12, weight: .semibold, color: .systemRed, alignment: .center), submit = yokaButton("Buat Akun & Verifikasi Email")
    private var busy = false
    override func viewDidLoad() {
        super.viewDidLoad()
        let back = UIButton(type: .system); back.setImage(UIImage(systemName: "chevron.left"), for: .normal); back.tintColor = Yoka.navy; back.widthAnchor.constraint(equalToConstant: 44).isActive = true
        back.addAction(UIAction { [weak self] _ in self?.dismiss(animated: true) }, for: .touchUpInside)
        let header = UIStackView(arrangedSubviews: [back, UIView(), logoView(width: 150), UIView()]); header.axis = .horizontal; header.alignment = .center; header.distribution = .equalCentering
        let row1 = UIStackView(arrangedSubviews: [nameField, phoneField]), row2 = UIStackView(arrangedSubviews: [emailField, passwordField])
        let phoneLayout = UIDevice.current.userInterfaceIdiom == .phone
        [row1, row2].forEach { $0.axis = phoneLayout ? .vertical : .horizontal; $0.spacing = phoneLayout ? 8 : 16; $0.distribution = .fillEqually }
        let legal = UIButton(type: .system); legal.setTitle("Dengan mendaftar, Anda menyetujui Syarat Layanan dan Kebijakan Privasi", for: .normal); legal.setTitleColor(Yoka.slate, for: .normal); legal.titleLabel?.font = .systemFont(ofSize: 12)
        legal.addAction(UIAction { [weak self] _ in let c = KasirWebViewController(path: "/syarat", modal: true); c.modalPresentationStyle = .fullScreen; self?.present(c, animated: true) }, for: .touchUpInside)
        let have = UIButton(type: .system); have.setTitle("Sudah punya akun? Masuk", for: .normal); have.setTitleColor(Yoka.greenDark, for: .normal); have.titleLabel?.font = .systemFont(ofSize: 14, weight: .bold)
        have.addAction(UIAction { [weak self] _ in guard let self else { return }; let window = self.view.window; self.dismiss(animated: false) { window?.rootViewController = KasirWebViewController(path: "/pos") } }, for: .touchUpInside)
        formError.isHidden = true; submit.addAction(UIAction { [weak self] _ in self?.submitForm() }, for: .touchUpInside)
        let stack = UIStackView(arrangedSubviews: [header, makeLabel("Buat akun Yoka POS", size: 23, weight: .bold, alignment: .center), makeLabel("Coba gratis 7 hari. Semua fitur terbuka, tanpa kartu kredit.", size: 14, color: Yoka.slate, alignment: .center), row1, row2, formError, submit, legal, have])
        stack.axis = .vertical; stack.spacing = 9
        let card = UIView(); card.backgroundColor = .white; card.layer.cornerRadius = 22; card.layer.borderColor = Yoka.border.cgColor; card.layer.borderWidth = 1
        card.addSubview(stack); stack.pin(to: card, inset: UIEdgeInsets(top: 18, left: 30, bottom: 18, right: 30)); view.addSubview(card); card.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([card.centerXAnchor.constraint(equalTo: view.centerXAnchor), card.centerYAnchor.constraint(equalTo: view.centerYAnchor), card.widthAnchor.constraint(equalTo: view.safeAreaLayoutGuide.widthAnchor, multiplier: phoneLayout ? 0.92 : 0.72), card.widthAnchor.constraint(lessThanOrEqualToConstant: 850), card.topAnchor.constraint(greaterThanOrEqualTo: view.safeAreaLayoutGuide.topAnchor, constant: 8), card.bottomAnchor.constraint(lessThanOrEqualTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -8)])
    }
    private func submitForm() {
        guard !busy else { return }; [nameField, phoneField, emailField, passwordField].forEach { $0.setError(nil) }; formError.isHidden = true
        let name = nameField.value.trimmingCharacters(in: .whitespacesAndNewlines), digits = phoneField.value.filter(\.isNumber).drop { $0 == "0" }
        let email = emailField.value.trimmingCharacters(in: .whitespacesAndNewlines), password = passwordField.value; var valid = true
        if name.isEmpty { nameField.setError("Nama wajib diisi."); valid = false }; if digits.count < 8 { phoneField.setError("Nomor HP belum lengkap."); valid = false }
        if !email.contains("@") || !email.contains(".") { emailField.setError("Email tidak valid."); valid = false }; if password.count < 8 { passwordField.setError("Password minimal 8 karakter."); valid = false }; guard valid else { return }
        setBusy(true); RegistrationService.register(name: name, phone: "0" + digits, email: email, password: password) { [weak self] result in DispatchQueue.main.async { self?.handle(result) } }
    }
    private func handle(_ result: RegistrationService.Result) {
        setBusy(false)
        if result.success { let store = WKWebsiteDataStore.default().httpCookieStore, group = DispatchGroup(); result.cookies.forEach { c in group.enter(); store.setCookie(c) { group.leave() } }; group.notify(queue: .main) { [weak self] in guard let self, let window = self.view.window else { return }; self.dismiss(animated: false); window.rootViewController = KasirWebViewController(path: "/email/verify") }; return }
        result.fields.forEach { key, message in
            let text = key == "email" && message.lowercased().range(of: "sudah|taken|terdaftar|digunakan", options: .regularExpression) != nil ? "Email ini sudah terdaftar. Silakan masuk." : message
            ["name": nameField, "phone": phoneField, "email": emailField, "password": passwordField][key]?.setError(text)
        }; if let message = result.general { formError.text = message; formError.isHidden = false }
    }
    private func setBusy(_ value: Bool) { busy = value; submit.isEnabled = !value; submit.alpha = value ? 0.65 : 1; submit.configuration?.title = value ? "Memproses..." : "Buat Akun & Verifikasi Email" }
}

final class FieldView: UIStackView {
    let field = UITextField(); private let error = makeLabel("", size: 11, color: .systemRed); var value: String { field.text ?? "" }
    init(title: String, placeholder: String, prefix: String? = nil, keyboard: UIKeyboardType = .default, secure: Bool = false, contentType: UITextContentType?) {
        super.init(frame: .zero); axis = .vertical; spacing = 4; addArrangedSubview(makeLabel(title, size: 13, weight: .bold))
        let holder = UIView(); holder.layer.cornerRadius = 10; holder.layer.borderWidth = 1; holder.layer.borderColor = Yoka.border.cgColor; holder.heightAnchor.constraint(equalToConstant: 46).isActive = true
        var leading = holder.leadingAnchor
        if let prefix { let p = makeLabel(prefix, size: 15, weight: .bold, color: Yoka.slate); holder.addSubview(p); p.translatesAutoresizingMaskIntoConstraints = false; NSLayoutConstraint.activate([p.leadingAnchor.constraint(equalTo: holder.leadingAnchor, constant: 13), p.centerYAnchor.constraint(equalTo: holder.centerYAnchor)]); leading = p.trailingAnchor }
        field.placeholder = placeholder; field.font = .systemFont(ofSize: 15); field.textColor = Yoka.ink; field.keyboardType = keyboard; field.isSecureTextEntry = secure; field.textContentType = contentType; field.autocapitalizationType = keyboard == .emailAddress ? .none : .words; field.autocorrectionType = .no
        holder.addSubview(field); field.translatesAutoresizingMaskIntoConstraints = false; NSLayoutConstraint.activate([field.leadingAnchor.constraint(equalTo: leading, constant: prefix == nil ? 13 : 8), field.trailingAnchor.constraint(equalTo: holder.trailingAnchor, constant: -13), field.topAnchor.constraint(equalTo: holder.topAnchor), field.bottomAnchor.constraint(equalTo: holder.bottomAnchor)])
        addArrangedSubview(holder); error.isHidden = true; addArrangedSubview(error)
    }
    required init(coder: NSCoder) { fatalError() }
    func setError(_ message: String?) { error.text = message; error.isHidden = message == nil; arrangedSubviews[1].layer.borderColor = message == nil ? Yoka.border.cgColor : UIColor.systemRed.cgColor }
}

enum RegistrationService {
    struct Result { var success = false; var general: String?; var fields: [String: String] = [:]; var cookies: [HTTPCookie] = [] }
    static func register(name: String, phone: String, email: String, password: String, completion: @escaping (Result) -> Void) {
        let cfg = URLSessionConfiguration.ephemeral; cfg.httpShouldSetCookies = true; cfg.httpCookieAcceptPolicy = .always; cfg.timeoutIntervalForRequest = 20
        let session = URLSession(configuration: cfg, delegate: NoRedirectDelegate(), delegateQueue: nil); var get = URLRequest(url: Yoka.url("/register")); get.setValue("text/html", forHTTPHeaderField: "Accept"); get.setValue(Yoka.userAgentSuffix, forHTTPHeaderField: "User-Agent")
        session.dataTask(with: get) { data, response, error in
            guard error == nil, let http = response as? HTTPURLResponse, let data else { completion(Result(general: "Tidak dapat terhubung ke server. Periksa koneksi internet lalu coba lagi.")); return }
            let cookies = cookiesFrom(http); if (300..<400).contains(http.statusCode) { completion(Result(success: true, cookies: cookies)); return }
            guard let token = firstMatch(#"name="_token"\s+value="([^"]+)""#, in: String(data: data, encoding: .utf8) ?? "") else { completion(Result(general: "Pendaftaran belum bisa dimulai. Coba lagi.")); return }
            let values = ["_token": token, "name": name, "phone": phone, "email": email, "password": password, "password_confirmation": password]
            var post = URLRequest(url: Yoka.url("/register")); post.httpMethod = "POST"; post.httpBody = values.map { "\(escape($0.key))=\(escape($0.value))" }.joined(separator: "&").data(using: .utf8)
            post.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type"); post.setValue("application/json", forHTTPHeaderField: "Accept"); post.setValue("XMLHttpRequest", forHTTPHeaderField: "X-Requested-With"); post.setValue(Yoka.baseURL.absoluteString + "/register", forHTTPHeaderField: "Referer"); post.setValue(Yoka.userAgentSuffix, forHTTPHeaderField: "User-Agent"); post.setValue(cookies.map { "\($0.name)=\($0.value)" }.joined(separator: "; "), forHTTPHeaderField: "Cookie")
            session.dataTask(with: post) { body, response, error in
                guard error == nil, let http = response as? HTTPURLResponse else { completion(Result(general: "Tidak dapat terhubung ke server. Periksa koneksi internet lalu coba lagi.")); return }; let all = cookies + cookiesFrom(http)
                switch http.statusCode {
                case 200..<400: completion(Result(success: true, cookies: all))
                case 419: completion(Result(general: "Sesi pendaftaran habis. Silakan coba lagi."))
                case 429: completion(Result(general: "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."))
                case 500...: completion(Result(general: "Server sedang bermasalah. Coba lagi sebentar lagi."))
                case 422: var result = Result(); if let body, let json = try? JSONSerialization.jsonObject(with: body) as? [String: Any], let errors = json["errors"] as? [String: [String]] { result.fields = errors.compactMapValues { $0.first } }; if result.fields.isEmpty { result.general = "Data pendaftaran belum valid. Periksa kembali isian Anda." }; completion(result)
                default: completion(Result(general: "Pendaftaran gagal (kode \(http.statusCode))."))
                }
            }.resume()
        }.resume()
    }
    private static func cookiesFrom(_ response: HTTPURLResponse) -> [HTTPCookie] { HTTPCookie.cookies(withResponseHeaderFields: response.allHeaderFields.reduce(into: [String: String]()) { if let k = $1.key as? String, let v = $1.value as? String { $0[k] = v } }, for: Yoka.baseURL) }
    private static func firstMatch(_ pattern: String, in text: String) -> String? { guard let r = try? NSRegularExpression(pattern: pattern), let m = r.firstMatch(in: text, range: NSRange(text.startIndex..., in: text)), let range = Range(m.range(at: 1), in: text) else { return nil }; return String(text[range]) }
    private static func escape(_ value: String) -> String { value.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed.subtracting(CharacterSet(charactersIn: "+&="))) ?? value }
}

final class NoRedirectDelegate: NSObject, URLSessionTaskDelegate {
    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse, newRequest request: URLRequest, completionHandler: @escaping (URLRequest?) -> Void) { completionHandler(nil) }
}

final class KasirWebViewController: YokaViewController, WKNavigationDelegate, WKUIDelegate, WKScriptMessageHandler {
    private static let roots = ["pos", "kasir", "shifts", "orders", "receipt", "kitchen", "login", "register", "logout", "forgot-password", "reset-password", "email", "mulai", "onboarding", "auth", "syarat", "privasi", "hapus-akun", "lang"]
    private static let plans = ["/saas/plans", "/saas/subscription", "/saas/checkout", "/subscription/expired"]
    private let initialPath: String, modalWeb: Bool
    private var webView: WKWebView!
    private let networkMonitor = NWPathMonitor()
    private let networkQueue = DispatchQueue(label: "com.yokapos.network-monitor")
    private let offlineBanner = UILabel()
    private let retryButton = yokaButton("Coba Lagi")
    private let sessionBanner = UILabel()
    private var hasLoadFailure = false
    private var isOnline = true
    private var transactionInFlight = false
    private var backgroundedAt: Date?
    init(path: String, modal: Bool = false) { initialPath = path; modalWeb = modal; super.init(nibName: nil, bundle: nil) }
    required init?(coder: NSCoder) { fatalError() }
    override func viewDidLoad() {
        super.viewDidLoad(); let config = WKWebViewConfiguration(); config.websiteDataStore = .default(); config.preferences.javaScriptCanOpenWindowsAutomatically = false
        config.applicationNameForUserAgent = Yoka.userAgentSuffix
        config.userContentController.add(self, name: "yokaPrint")
        config.userContentController.add(self, name: "yokaState")
        config.userContentController.addUserScript(WKUserScript(source: Self.nativeBridgeJS, injectionTime: .atDocumentStart, forMainFrameOnly: false))
        config.userContentController.addUserScript(WKUserScript(source: Self.iPhonePortraitJS, injectionTime: .atDocumentEnd, forMainFrameOnly: true))
        config.userContentController.addUserScript(WKUserScript(source: Self.hideJS, injectionTime: .atDocumentEnd, forMainFrameOnly: false))
        webView = WKWebView(frame: .zero, configuration: config); webView.navigationDelegate = self; webView.uiDelegate = self; webView.isOpaque = false; webView.backgroundColor = .white
        webView.allowsBackForwardNavigationGestures = true
        webView.scrollView.keyboardDismissMode = .interactive
        view.addSubview(webView); webView.pin(to: view)
        configureConnectionUI()
        startNetworkMonitor()
        NotificationCenter.default.addObserver(self, selector: #selector(appBecameActive), name: .yokaSceneDidBecomeActive, object: nil)
        NotificationCenter.default.addObserver(self, selector: #selector(appEnteredBackground), name: .yokaSceneDidEnterBackground, object: nil)
        if modalWeb { let close = UIButton(type: .system); close.setImage(UIImage(systemName: "xmark.circle.fill"), for: .normal); close.tintColor = Yoka.navy; close.backgroundColor = .white; close.layer.cornerRadius = 20; close.addAction(UIAction { [weak self] _ in self?.dismiss(animated: true) }, for: .touchUpInside); view.addSubview(close); close.translatesAutoresizingMaskIntoConstraints = false; NSLayoutConstraint.activate([close.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor, constant: 12), close.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -16), close.widthAnchor.constraint(equalToConstant: 40), close.heightAnchor.constraint(equalToConstant: 40)]) }
        webView.load(URLRequest(url: Yoka.url(initialPath)))
    }
    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        UIApplication.shared.isIdleTimerDisabled = true
    }
    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        UIApplication.shared.isIdleTimerDisabled = false
    }
    deinit {
        networkMonitor.cancel()
        webView?.configuration.userContentController.removeScriptMessageHandler(forName: "yokaPrint")
        webView?.configuration.userContentController.removeScriptMessageHandler(forName: "yokaState")
        NotificationCenter.default.removeObserver(self)
    }
    func webView(_ w: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = action.request.url, url.scheme == "https" else { decisionHandler(.cancel); return }
        guard url.host == Yoka.baseURL.host else { UIApplication.shared.open(url); decisionHandler(.cancel); return }
        let path = normalized(url.path)
        if path.hasPrefix("/receipt/"), path.hasSuffix("/print"), URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems?.contains(where: { $0.name == "via" && $0.value == "rawbt" }) == true {
            decisionHandler(.cancel)
            DispatchQueue.main.async { [weak self] in self?.presentReceiptPreview(url: url) }
            return
        }
        if path == "/dashboard" || path == "/" && initialPath == "/pos" { decisionHandler(.cancel); w.load(URLRequest(url: Yoka.url("/pos"))); return }
        if Self.plans.contains(where: { path == $0 || path.hasPrefix($0 + "/") }) { decisionHandler(.cancel); notice("Langganan dikelola di web", "Langganan Yoka POS dikelola melalui akun Anda di web dan tidak dijual di aplikasi kasir."); return }
        guard allowed(path) else { decisionHandler(.cancel); notice("Dibuka lewat web", "Dashboard, laporan, produk, dan pengaturan bisnis tidak ada di aplikasi kasir."); return }; decisionHandler(.allow)
    }
    func webView(_ w: WKWebView, didFinish navigation: WKNavigation!) {
        hasLoadFailure = false
        retryButton.isHidden = true
        guard let url = w.url else { return }; let p = normalized(url.path)
        let wasSignedIn = UserDefaults.standard.bool(forKey: Yoka.signedInKey)
        if ["/pos", "/shifts", "/orders", "/receipt", "/kitchen", "/mulai", "/onboarding"].contains(where: { p == $0 || p.hasPrefix($0 + "/") }) {
            UserDefaults.standard.set(true, forKey: Yoka.signedInKey)
            sessionBanner.isHidden = true
        }
        else if ["/", "/login", "/register", "/kasir", "/forgot-password", "/reset-password", "/auth", "/email", "/syarat", "/privasi", "/hapus-akun"].contains(where: { p == $0 || p.hasPrefix($0 + "/") }) {
            UserDefaults.standard.set(false, forKey: Yoka.signedInKey)
            if wasSignedIn && (p == "/login" || p == "/kasir") { showSessionBanner() }
        }
        if ["/pos", "/shifts", "/orders", "/kitchen"].contains(where: { p == $0 || p.hasPrefix($0 + "/") }) {
            UserDefaults.standard.set(p, forKey: Yoka.lastKasirPathKey)
        }
        w.evaluateJavaScript(Self.iPhonePortraitJS)
        w.evaluateJavaScript(Self.hideJS)
        if p == "/pos" { showGuidedAccessTipIfNeeded() }
    }
    func webView(_ w: WKWebView, didFailProvisionalNavigation n: WKNavigation!, withError e: Error) {
        guard (e as NSError).code != NSURLErrorCancelled else { return }
        hasLoadFailure = true
        retryButton.isHidden = false
        updateConnectionUI(online: isOnline)
    }
    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
        hasLoadFailure = true
        retryButton.isHidden = false
    }
    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        switch message.name {
        case "yokaPrint":
            guard let value = message.body as? [String: Any], let rawURL = value["url"] as? String,
                  let url = URL(string: rawURL), url.host == Yoka.baseURL.host,
                  normalized(url.path).hasPrefix("/receipt/") else { return }
            presentReceiptPreview(url: url)
        case "yokaState":
            guard let value = message.body as? [String: Any], let state = value["transaction"] as? String else { return }
            transactionInFlight = state == "started"
        default: break
        }
    }
    func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
        let lower = message.lowercased()
        let alert = UIAlertController(title: lower.contains("sesi") ? "Sesi kasir berakhir" : "Yoka POS", message: message, preferredStyle: .alert)
        if lower.contains("sesi") || lower.contains("401") || lower.contains("403") || lower.contains("419") {
            alert.addAction(UIAlertAction(title: "Masuk Kembali", style: .default) { [weak self] _ in completionHandler(); self?.webView.load(URLRequest(url: Yoka.url("/kasir"))) })
        } else if lower.contains("koneksi") || lower.contains("server") || lower.contains("order") || lower.contains("pembayaran") {
            alert.addAction(UIAlertAction(title: "Cek Riwayat", style: .default) { [weak self] _ in completionHandler(); self?.webView.load(URLRequest(url: Yoka.url("/orders"))) })
        }
        alert.addAction(UIAlertAction(title: "Tutup", style: .cancel) { _ in completionHandler() })
        present(alert, animated: true)
    }
    private func configureConnectionUI() {
        offlineBanner.text = "Tidak ada koneksi internet"
        offlineBanner.textAlignment = .center
        offlineBanner.textColor = .white
        offlineBanner.backgroundColor = UIColor(red: 180/255, green: 83/255, blue: 9/255, alpha: 0.96)
        offlineBanner.font = .systemFont(ofSize: 13, weight: .bold)
        offlineBanner.isHidden = true
        view.addSubview(offlineBanner)
        sessionBanner.text = "Sesi berakhir. Silakan masuk kembali."
        sessionBanner.textAlignment = .center
        sessionBanner.textColor = .white
        sessionBanner.backgroundColor = Yoka.navy.withAlphaComponent(0.96)
        sessionBanner.font = .systemFont(ofSize: 13, weight: .semibold)
        sessionBanner.isHidden = true
        view.addSubview(sessionBanner)
        offlineBanner.translatesAutoresizingMaskIntoConstraints = false
        sessionBanner.translatesAutoresizingMaskIntoConstraints = false
        retryButton.isHidden = true
        retryButton.addAction(UIAction { [weak self] _ in self?.retry() }, for: .touchUpInside)
        view.addSubview(retryButton)
        retryButton.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            offlineBanner.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            offlineBanner.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            offlineBanner.topAnchor.constraint(equalTo: view.topAnchor),
            offlineBanner.heightAnchor.constraint(equalToConstant: 32),
            sessionBanner.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            sessionBanner.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            sessionBanner.topAnchor.constraint(equalTo: offlineBanner.bottomAnchor),
            sessionBanner.heightAnchor.constraint(equalToConstant: 34),
            retryButton.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            retryButton.centerYAnchor.constraint(equalTo: view.centerYAnchor),
            retryButton.widthAnchor.constraint(equalToConstant: 170),
        ])
    }
    private func startNetworkMonitor() {
        networkMonitor.pathUpdateHandler = { [weak self] path in
            let online = path.status == .satisfied
            DispatchQueue.main.async {
                guard let self else { return }
                let wasOnline = self.isOnline
                self.isOnline = online
                self.updateConnectionUI(online: online)
                if online && !wasOnline && self.hasLoadFailure { self.retry() }
            }
        }
        networkMonitor.start(queue: networkQueue)
    }
    private func updateConnectionUI(online: Bool) {
        offlineBanner.isHidden = online
        if !online { retryButton.isHidden = false }
    }
    private func retry() {
        guard isOnline else { return }
        retryButton.isHidden = true
        if let url = webView.url, url.host == Yoka.baseURL.host { webView.reload() }
        else { webView.load(URLRequest(url: Yoka.url(initialPath))) }
    }
    @objc private func appBecameActive() {
        UIApplication.shared.isIdleTimerDisabled = true
        if hasLoadFailure && isOnline { retry(); return }
        if transactionInFlight && presentedViewController == nil {
            transactionInFlight = false
            let alert = UIAlertController(title: "Periksa transaksi terakhir", message: "Aplikasi sempat masuk background ketika pembayaran diproses. Cek Riwayat Transaksi sebelum mencoba bayar lagi agar order tidak tercatat dua kali.", preferredStyle: .alert)
            alert.addAction(UIAlertAction(title: "Cek Riwayat", style: .default) { [weak self] _ in self?.webView.load(URLRequest(url: Yoka.url("/orders"))) })
            alert.addAction(UIAlertAction(title: "Tetap di Kasir", style: .cancel))
            present(alert, animated: true)
        }
        guard let backgroundedAt, Date().timeIntervalSince(backgroundedAt) > 2 else { return }
        self.backgroundedAt = nil
        webView.evaluateJavaScript("document.readyState") { [weak self] value, error in
            guard let self, error != nil || value == nil else { return }
            self.retry()
        }
    }
    @objc private func appEnteredBackground() {
        backgroundedAt = Date()
        if let path = webView.url?.path, allowed(normalized(path)) {
            UserDefaults.standard.set(normalized(path), forKey: Yoka.lastKasirPathKey)
        }
    }
    private func showSessionBanner() {
        sessionBanner.isHidden = false
        view.bringSubviewToFront(sessionBanner)
    }
    private func showGuidedAccessTipIfNeeded() {
        guard !UIAccessibility.isGuidedAccessEnabled,
              !UserDefaults.standard.bool(forKey: Yoka.guidedAccessTipKey), presentedViewController == nil else { return }
        UserDefaults.standard.set(true, forKey: Yoka.guidedAccessTipKey)
        let alert = UIAlertController(title: "Kunci perangkat untuk kasir", message: "Aktifkan Guided Access di Pengaturan > Aksesibilitas. Saat POS dibuka, tekan tombol samping tiga kali agar kasir tidak keluar dari aplikasi.", preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "Mengerti", style: .default))
        present(alert, animated: true)
    }
    private func presentReceiptPreview(url: URL) {
        guard presentedViewController == nil else { return }
        let clean = URLComponents(url: url, resolvingAgainstBaseURL: false).flatMap { parts -> URL? in
            var copy = parts
            copy.queryItems = [URLQueryItem(name: "via", value: "browser")]
            return copy.url
        } ?? url
        let preview = ReceiptPreviewViewController(url: clean)
        let navigation = UINavigationController(rootViewController: preview)
        navigation.modalPresentationStyle = UIDevice.current.userInterfaceIdiom == .pad ? .formSheet : .fullScreen
        present(navigation, animated: true)
    }
    private func normalized(_ s: String) -> String { let t = s.count > 1 ? s.trimmingCharacters(in: CharacterSet(charactersIn: "/")) : ""; return t.isEmpty ? "/" : "/" + t }
    private func allowed(_ p: String) -> Bool { p != "/" && Self.roots.contains { p == "/\($0)" || p.hasPrefix("/\($0)/") } }
    private func notice(_ title: String, _ message: String) { guard presentedViewController == nil else { return }; let a = UIAlertController(title: title, message: message, preferredStyle: .alert); a.addAction(UIAlertAction(title: "Mengerti", style: .default)); present(a, animated: true) }
    private static let nativeBridgeJS = #"""
    (function(){
      window.print=function(){
        try{window.webkit.messageHandlers.yokaPrint.postMessage({url:location.href});}catch(e){}
      };
      function configureIOSPrinting(){
        if(location.pathname!='/pos')return;
        try{localStorage.setItem('pos_print_via','browser');}catch(e){}
        try{
          var register=document.querySelector('[x-data="posApp()"]');
          if(window.Alpine&&register)Alpine.$data(register).printVia='browser';
          document.querySelectorAll('[x-data]').forEach(function(el){
            if((el.getAttribute('x-data')||'').indexOf('Bluetooth (RawBT)')<0)return;
            var data=window.Alpine&&Alpine.$data(el);
            if(data)data.opts=[{v:'browser',l:'Print Preview / AirPrint'},{v:'none',l:'Tanpa cetak'}];
          });
        }catch(e){}
      }
      document.addEventListener('DOMContentLoaded',function(){setTimeout(configureIOSPrinting,250);setTimeout(configureIOSPrinting,1000);});
      if(window.__yokaFetchWrapped)return;window.__yokaFetchWrapped=true;
      var originalFetch=window.fetch;
      window.fetch=function(input,init){
        var url=typeof input==='string'?input:(input&&input.url)||'';
        var isSale=/\/pos\/order(?:\?|$)/.test(url);
        if(isSale){try{window.webkit.messageHandlers.yokaState.postMessage({transaction:'started'});}catch(e){}}
        return originalFetch.apply(this,arguments).finally(function(){
          if(isSale){try{window.webkit.messageHandlers.yokaState.postMessage({transaction:'finished'});}catch(e){}}
        });
      };
    })();
    """#
    private static let iPhonePortraitJS = #"""
    (function(){
      if(!/iPhone|iPod/.test(navigator.userAgent))return;
      var viewport=document.querySelector('meta[name="viewport"]');
      if(!viewport){viewport=document.createElement('meta');viewport.name='viewport';document.head.appendChild(viewport);}
      viewport.content='width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover';
      document.documentElement.classList.add('yoka-ios-app');
      document.body&&document.body.classList.add('yoka-ios-app');
      document.documentElement.dataset.yokaPath=location.pathname;
      if(document.getElementById('yoka-ios-portrait'))return;
      var style=document.createElement('style');style.id='yoka-ios-portrait';style.textContent=`
        @media (max-width: 600px) and (orientation: portrait) {
          html.yoka-ios-app,html.yoka-ios-app body{max-width:100vw;overflow-x:hidden;-webkit-text-size-adjust:100%;}
          html.yoka-ios-app input,html.yoka-ios-app textarea,html.yoka-ios-app select{font-size:16px!important;}
          html.yoka-ios-app button,html.yoka-ios-app a{-webkit-tap-highlight-color:transparent;touch-action:manipulation;}

          /* Cart sheet and its floating opener stay above the home indicator. */
          [\:class*="mobileCart ?"]{padding-bottom:env(safe-area-inset-bottom)!important;}
          [x-show="!mobileCart"]{bottom:max(12px,env(safe-area-inset-bottom))!important;min-height:56px;}

          /* Product choice, item note, hold order, and held-order list become
             reachable bottom sheets instead of small desktop dialogs. */
          [x-show="variantModal"],[x-show="noteModal"],[x-show="draftModal"],[x-show="holdModal"]{
            align-items:flex-end!important;justify-content:center!important;padding:0!important;
          }
          [x-show="variantModal"]>div,[x-show="noteModal"]>div,[x-show="draftModal"]>div,[x-show="holdModal"]>form{
            margin:auto 0 0!important;width:100%!important;max-width:none!important;
            max-height:92dvh!important;overflow-y:auto!important;
            border-radius:22px 22px 0 0!important;padding-bottom:max(20px,env(safe-area-inset-bottom))!important;
          }
          [x-show="draftModal"]>div{overflow:hidden!important;}

          /* Checkout is a full-height work area on iPhone. Its action row stays
             visible while payment details and the keyboard can scroll. */
          [x-show="payModal"]{align-items:stretch!important;padding:0!important;overflow:hidden!important;}
          [x-show="payModal"]>div{
            margin:0!important;width:100%!important;max-width:none!important;min-height:100dvh!important;
            max-height:100dvh!important;border-radius:0!important;display:flex!important;flex-direction:column!important;
          }
          [x-show="payModal"]>div>div{max-height:100dvh!important;min-height:100dvh!important;}
          [x-show="payModal"]>div>div>div:last-child{padding-bottom:max(12px,env(safe-area-inset-bottom))!important;}

          /* Successful payment remains fully readable on short phones. */
          [x-show="successModal"]{align-items:flex-end!important;padding:0!important;overflow-y:auto!important;}
          [x-show="successModal"]>div{
            margin-top:auto!important;width:100%!important;max-width:none!important;max-height:94dvh!important;
            overflow-y:auto!important;border-radius:22px 22px 0 0!important;
            padding-bottom:env(safe-area-inset-bottom)!important;
          }

          [x-show="variantModal"] button,[x-show="noteModal"] button,[x-show="payModal"] button,
          [x-show="draftModal"] button,[x-show="holdModal"] button,[x-show="successModal"] button{
            min-height:44px;
          }
          [x-show="draftModal"] .flex-1.overflow-y-auto{overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}

          /* Transaction history already supplies mobile cards; preserve that
             layout and give filters/cards comfortable iPhone touch spacing. */
          html[data-yoka-path^="/orders"] body{padding-bottom:max(20px,env(safe-area-inset-bottom));}
          html[data-yoka-path^="/orders"] .page>section{max-width:100%;}
          html[data-yoka-path^="/orders"] .page form input[type="date"]{min-height:44px;width:100%!important;}
          html[data-yoka-path^="/orders"] .page .md\:hidden>a{min-height:76px;display:block;}
        }
      `;document.head.appendChild(style);
    })();
    """#
    private static let hideJS = #"""
    (function(){var ok=/^\/(pos|kasir|shifts|orders|receipt|kitchen|login|register|logout|forgot-password|reset-password|email|mulai|onboarding|auth|syarat|privasi|hapus-akun|lang)(\/.*)?$/;function scan(){document.querySelectorAll('a[href]').forEach(function(a){try{var u=new URL(a.href,location.href);if(u.origin!==location.origin)return;var p=u.pathname.replace(/\/+$/,'')||'/';if(!ok.test(p)||p.indexOf('/auth/google')===0||p.indexOf('/saas/')===0||p.indexOf('/subscription/expired')===0)a.style.display='none';}catch(e){}});}scan();setTimeout(scan,500);setTimeout(scan,1500);})();
    """#
}

final class ReceiptPreviewViewController: UIViewController, WKNavigationDelegate {
    private let url: URL
    private let webView: WKWebView
    private let activity = UIActivityIndicatorView(style: .medium)

    init(url: URL) {
        self.url = url
        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .default()
        configuration.applicationNameForUserAgent = Yoka.userAgentSuffix
        configuration.userContentController.addUserScript(WKUserScript(source: "window.print=function(){};", injectionTime: .atDocumentStart, forMainFrameOnly: false))
        webView = WKWebView(frame: .zero, configuration: configuration)
        super.init(nibName: nil, bundle: nil)
    }
    required init?(coder: NSCoder) { fatalError() }

    override func viewDidLoad() {
        super.viewDidLoad()
        title = "Preview Struk"
        view.backgroundColor = Yoka.surface
        webView.navigationDelegate = self
        webView.isOpaque = false
        webView.backgroundColor = .white
        view.addSubview(webView)
        webView.pin(to: view)
        navigationItem.leftBarButtonItem = UIBarButtonItem(title: "Tutup", style: .plain, target: self, action: #selector(close))
        navigationItem.rightBarButtonItem = UIBarButtonItem(title: "AirPrint", style: .done, target: self, action: #selector(printReceipt))
        navigationItem.rightBarButtonItem?.isEnabled = false
        activity.hidesWhenStopped = true
        navigationItem.titleView = activity
        activity.startAnimating()
        webView.load(URLRequest(url: url, cachePolicy: .reloadRevalidatingCacheData, timeoutInterval: 30))
    }
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        activity.stopAnimating()
        navigationItem.titleView = nil
        title = "Preview Struk"
        navigationItem.rightBarButtonItem?.isEnabled = true
    }
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        activity.stopAnimating()
        navigationItem.titleView = nil
        let alert = UIAlertController(title: "Preview gagal dimuat", message: "Periksa koneksi internet lalu coba lagi.", preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "Coba Lagi", style: .default) { [weak self] _ in guard let self else { return }; self.activity.startAnimating(); self.webView.load(URLRequest(url: self.url)) })
        alert.addAction(UIAlertAction(title: "Tutup", style: .cancel))
        present(alert, animated: true)
    }
    @objc private func close() { dismiss(animated: true) }
    @objc private func printReceipt() {
        let controller = UIPrintInteractionController.shared
        let info = UIPrintInfo(dictionary: nil)
        info.outputType = .general
        info.jobName = title ?? "Struk Yoka POS"
        controller.printInfo = info
        controller.printFormatter = webView.viewPrintFormatter()
        if UIDevice.current.userInterfaceIdiom == .pad, let button = navigationItem.rightBarButtonItem {
            controller.present(from: button, animated: true)
        } else {
            controller.present(animated: true)
        }
    }
}
