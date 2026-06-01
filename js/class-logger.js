/*============================================================
Classe de logging javascript
V0.2 - File d'attente pour garantir la chronologie
============================================================*/
class LoggerJS {
    constructor(logFile, ligneDate = true, afficherIP = false) {
        this.logFile = logFile;
        this.ligneDate = ligneDate;
        this.afficherIP = afficherIP;
        this._queue = Promise.resolve(); // File d'attente initialisée à une promesse résolue
    }

    log(message) {
        // Chaque log est chaîné à la fin de la file
        this._queue = this._queue.then(() => this._sendLog(message));
    }

    _sendLog(message) {
        const data = new FormData();
        data.append('action', 'cl_log_message');
        data.append('message', message);
        data.append('logFile', this.logFile);
        data.append('ligneDate', this.ligneDate);
        data.append('afficherIP', this.afficherIP);

        return fetch(cl_ajax_object.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Réponse du serveur: ", data.data);
            } else {
                console.error("Erreur: ", data.data);
            }
        })
        .catch(error => {
            console.error('Erreur lors de l\'envoi du message:', error);
        });
    }
}
//console.log('********* LoggerJS chargé *****************');
/*
// Exemple d'utilisation
const myLoggerJS = new LoggerJS('messages.log');
myLoggerJS.log("Ceci est un message de log depuis le plugin WordPress.");
*/