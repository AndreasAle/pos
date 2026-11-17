import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.yokapos.app',
    appName: 'Yoka POS',
    webDir: 'native/www',
    server: {
        url: 'https://yokapos.com',
    },
    ios: {
        contentInset: 'automatic',
        backgroundColor: '#F8FAFC',
        preferredContentMode: 'mobile',
    },
    plugins: {
        StatusBar: {
            style: 'LIGHT',
            backgroundColor: '#0B1B3F',
            overlaysWebView: false,
        },
    },
};

export default config;
