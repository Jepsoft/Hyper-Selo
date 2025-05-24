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
let progressWindow = null;

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

// 🌀 Create Progress Window
const createProgressWindow = () => {
    if (progressWindow) {
        progressWindow.show();
        return;
    }

    progressWindow = new BrowserWindow({
        width: 400,
        height: 250,
        resizable: false,
        frame: false,
        show: false,
        transparent: true,
        alwaysOnTop: true,
        webPreferences: {
            nodeIntegration: true,
            contextIsolation: false
        }
    });

    progressWindow.loadFile(path.join(__dirname, 'App', 'update-progress.html'));

    progressWindow.on('closed', () => {
        progressWindow = null;
    });

    progressWindow.once('ready-to-show', () => {
        progressWindow.show();
    });

    return progressWindow;
};

// 🔄 Auto Updater Logic
const setupAutoUpdater = () => {
    if (!autoUpdater) return;

    autoUpdater.checkForUpdatesAndNotify();

    autoUpdater.on('update-available', () => {
        dialog.showMessageBox({
            type: 'info',
            title: 'Update Available',
            message: 'A new version is available!',
            detail: 'Would you like to download and install it now?',
            buttons: ['Download Update', 'Remind Me Later'],
            defaultId: 0,
            cancelId: 1
        }).then(result => {
            if (result.response === 0) {
                // User chose to download update
                const progressWin = createProgressWindow();
                
                // Update progress window with download status
                progressWin.webContents.send('update-status', {
                    status: 'Downloading update...',
                    percent: 0,
                    speed: '0 KB/s'
                });

                // Track download progress
                autoUpdater.on('download-progress', (progressObj) => {
                    const percent = Math.floor(progressObj.percent);
                    const speed = Math.floor(progressObj.bytesPerSecond / 1024);
                    progressWin.webContents.send('update-status', {
                        status: 'Downloading update...',
                        percent: percent,
                        speed: `${speed} KB/s`
                    });
                });

                autoUpdater.on('update-downloaded', () => {
                    progressWin.webContents.send('update-status', {
                        status: 'Update downloaded!',
                        percent: 100,
                        speed: '0 KB/s'
                    });

                    // Show restart prompt after 1.5 seconds
                    setTimeout(() => {
                        if (progressWin) progressWin.close();
                        
                        dialog.showMessageBox({
                            type: 'info',
                            title: 'Update Ready',
                            message: 'Update downloaded successfully!',
                            detail: 'The application will restart to install the update.',
                            buttons: ['Restart Now', 'Later'],
                            defaultId: 0,
                            cancelId: 1
                        }).then(restartResult => {
                            if (restartResult.response === 0) {
                                autoUpdater.quitAndInstall();
                            }
                        });
                    }, 1500);
                });
            }
        });
    });

    autoUpdater.on('error', (err) => {
        console.error("⚠ Auto-updater error:", err);
        if (progressWindow) progressWindow.close();
        
        dialog.showMessageBox({
            type: 'error',
            title: 'Update Error',
            message: 'Failed to download update',
            detail: err.toString()
        });
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
    
    if (progressWindow) {
        progressWindow.close();
    }
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});