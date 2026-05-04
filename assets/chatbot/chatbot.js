// assets/chatbot/chatbot.js
class ChatbotUI {
    constructor() {
        this.sessionId = this.getSessionId();
        this.isOpen = false;
        this.isLoading = false;
        this.init();
    }
    
    getSessionId() {
        let sessionId = localStorage.getItem('chatbot_session_id');
        if (!sessionId) {
            sessionId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('chatbot_session_id', sessionId);
        }
        return sessionId;
    }
    
    init() {
        this.createToggleButton();
        this.createChatContainer();
        this.bindEvents();
        console.log('Chatbot inicializado - Session:', this.sessionId);
    }
    
    createToggleButton() {
        this.toggleBtn = document.createElement('button');
        this.toggleBtn.className = 'chatbot-toggle';
        this.toggleBtn.innerHTML = '💬';
        this.toggleBtn.title = 'Abrir chat con el asistente';
        this.toggleBtn.setAttribute('aria-label', 'Abrir chat');
        document.body.appendChild(this.toggleBtn);
    }
    
    createChatContainer() {
        this.container = document.createElement('div');
        this.container.className = 'chatbot-container hidden';
        this.container.innerHTML = `
            <div class="chatbot-header">
                <h3>Asistente Virtual</h3>
                <button class="chatbot-clear" title="Nueva conversación" aria-label="Limpiar chat">🔄</button>
                <button class="chatbot-close" aria-label="Cerrar chat">✕</button>
            </div>
            <div class="chatbot-messages" id="chatbot-messages">
                <div class="message bot-message">
                    ¡Hola! 👋 Soy tu asistente virtual. ¿En qué puedo ayudarte hoy?
                </div>
            </div>
            <div class="chatbot-input-container">
                <div class="chatbot-input-wrapper">
                    <input type="text" class="chatbot-input" id="chatbot-input" 
                           placeholder="Escribe tu mensaje..." maxlength="500">
                    <button class="chatbot-send" id="chatbot-send" aria-label="Enviar mensaje">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(this.container);
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.input = document.getElementById('chatbot-input');
        this.sendBtn = document.getElementById('chatbot-send');
    }
    
    bindEvents() {
        this.toggleBtn.addEventListener('click', () => this.toggleChat());
        this.container.querySelector('.chatbot-close').addEventListener('click', () => this.toggleChat());
        this.container.querySelector('.chatbot-clear').addEventListener('click', () => this.clearChat());
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (this.isOpen && 
                !this.container.contains(e.target) && 
                !this.toggleBtn.contains(e.target)) {
                this.toggleChat();
            }
        });
    }
    
    toggleChat() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            this.container.classList.remove('hidden');
            this.input.focus();
            // Scroll al final
            this.scrollToBottom();
        } else {
            this.container.classList.add('hidden');
        }
    }
    
    async sendMessage() {
        const message = this.input.value.trim();
        
        if (!message || this.isLoading) return;
        
        // Agregar mensaje del usuario
        this.addMessage(message, 'user');
        this.input.value = '';
        this.setLoading(true);
        
        try {
            // La ruta es relativa a la página que carga este JS.
            // Como chatbot.js se incluye desde /public/, /admin/ y /logistico/,
            // todas apuntan igual: ../includes/chatbot_handler.php
            const response = await fetch('../includes/chatbot_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });

            const data = await response.json();

            if (data.success) {
                this.addMessage(data.reply, 'bot');
            } else {
                this.addMessage('🤖 Lo siento, ocurrió un error inesperado. Por favor, intenta nuevamente.', 'bot');
            }
            
        } catch (error) {
            console.error('Chatbot Error:', error);
            this.addMessage('🤖 Error de conexión. Por favor, verifica tu internet e intenta nuevamente.', 'bot');
        } finally {
            this.setLoading(false);
        }
    }
    
    addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        messageDiv.textContent = text;
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    setLoading(loading) {
        this.isLoading = loading;
        this.sendBtn.disabled = loading;
        this.input.disabled = loading;
        
        if (loading) {
            this.sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.input.placeholder = 'Enviando...';
        } else {
            this.sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            this.input.placeholder = 'Escribe tu mensaje...';
        }
    }
    
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    async clearChat() {
        const response = await fetch('../includes/chatbot_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear' })
        });
        const data = await response.json();
        this.messagesContainer.innerHTML = '';
        this.addMessage(data.reply, 'bot');
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ChatbotUI();
    });
} else {
    new ChatbotUI();
}