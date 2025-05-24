const { app, BrowserWindow, dialog } = require('electron');
const path = require('path');
const { exec } = require('child_process');

let autoUpdater;
try {
    autoUpdater = require('electron-updater').autoUpdater;
} catch (e) {
    console.warn("⚠ Could not load 'electron-updater'. It might be missing in dev mode or not bundled.");
}

let mainWindow = null;
let phpServerProcess = null;

// 🐘 Start PHP server
const startPHPServer = () => {
    const isPackaged = app.isPackaged;
    const phpFolder = isPackaged
        ? path.join(process.resourcesPath, 'app.asar.unpacked', 'php')
        : path.join(__dirname, 'php');

    const phpExecutable = 'C:\\xampp\\php\\php.exe'; // Change if needed
    const phpCommand = `"${phpExecutable}" -S 127.0.0.1:8000 -t "${phpFolder}"`;

    console.log(`🚀 Starting PHP Server: ${phpCommand}`);

    phpServerProcess = exec(phpCommand, (error, stdout, stderr) => {
        if (error) console.error(`❌ PHP Server Error: ${error.message}`);
        if (stderr) console.warn(`⚠ PHP Server Warning: ${stderr}`);
        if (stdout) console.log(`✅ PHP Server Output:\n${stdout}`);
    });

    phpServerProcess.on('error', (err) => {
        console.error('❌ Failed to start PHP server:', err);
    });
};

// 🪟 Create Main Window
const createMainWindow = () => {
    if (mainWindow) {
        mainWindow.show();
        return;
    }

    mainWindow = new BrowserWindow({
        width: 1280,
        height: 720,
        resizable: true,
        autoHideMenuBar: true,
        webPreferences: {
            nodeIntegration: true,
            contextIsolation: false,
            devTools: true,
            v8CacheOptions: 'bypassHeatCheck'
        }
    });

    mainWindow.loadFile(path.join(__dirname, 'App', 'index.html'));

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    mainWindow.webContents.session.clearStorageData()
        .then(() => console.log("🧹 Cleared session storage data."))
        .catch(error => console.error("⚠ Error clearing storage data:", error));
};

// 🔄 Auto Updater Logic
const setupAutoUpdater = () => {
    if (!autoUpdater) return;

    autoUpdater.checkForUpdatesAndNotify();

    autoUpdater.on('update-available', () => {
        dialog.showMessageBox({
            type: 'info',
            title: 'Update Available',
            message: 'A new update is being downloaded.'
        });
    });

    autoUpdater.on('update-downloaded', () => {
        dialog.showMessageBox({
            type: 'info',
            title: 'Update Ready',
            message: 'Update downloaded. Restart now to apply?',
            buttons: ['Yes', 'Later']
        }).then(result => {
            if (result.response === 0) {
                autoUpdater.quitAndInstall();
            }
        });
    });

    autoUpdater.on('error', (err) => {
        console.error("⚠ Auto-updater error:", err);
    });
};

// 🚀 App Ready
app.whenReady().then(() => {
    startPHPServer();
    createMainWindow();
    setupAutoUpdater();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createMainWindow();
        }
    });
});

// 🔚 Clean up on Quit
app.on('before-quit', () => {
    if (phpServerProcess) {
        console.log("🛑 Stopping PHP server...");
        phpServerProcess.kill('SIGTERM');
        phpServerProcess = null;
    }
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
