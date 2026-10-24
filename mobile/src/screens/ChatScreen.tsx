import React, { useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Screen from '../components/Screen';
import { chat as chatApi } from '../api/endpoints';
import type { ChatResponse } from '../api/types';
import { colors, font, radius, spacing } from '../theme/tokens';
import type { RootStackParamList } from '../navigation/RootNavigator';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';

type Mensaje = {
  id: number;
  rol: 'usuario' | 'asistente';
  texto: string;
  ticket?: { id: number; folio: string } | null;
};

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, 'Chat'>;
};

let contador = 0;
const siguienteId = () => ++contador;

export default function ChatScreen({ navigation }: Props) {
  const [mensajes, setMensajes] = useState<Mensaje[]>([
    {
      id: siguienteId(),
      rol: 'asistente',
      texto:
        'Hola, soy ORION. Describe una falla para registrar un ticket, o pregúntame sobre los manuales técnicos.',
    },
  ]);
  const [entrada, setEntrada] = useState('');
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    const texto = entrada.trim();
    if (!texto || enviando) return;

    setMensajes((prev) => [...prev, { id: siguienteId(), rol: 'usuario', texto }]);
    setEntrada('');
    setEnviando(true);

    try {
      const respuesta: ChatResponse = await chatApi(texto);
      setMensajes((prev) => [
        ...prev,
        {
          id: siguienteId(),
          rol: 'asistente',
          texto: respuesta.respuesta,
          ticket: respuesta.ticket,
        },
      ]);
    } catch (e) {
      setMensajes((prev) => [
        ...prev,
        {
          id: siguienteId(),
          rol: 'asistente',
          texto: e instanceof Error ? e.message : 'Error de conexión',
        },
      ]);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Screen titulo="Chat con ORION" subtitulo="Asistente técnico con IA" scroll={false}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={90}
      >
        <FlatList
          data={mensajes}
          keyExtractor={(m) => String(m.id)}
          style={styles.lista}
          contentContainerStyle={styles.listaContenido}
          renderItem={({ item }) => (
            <View style={[styles.burbuja, item.rol === 'usuario' ? styles.usuario : styles.asistente]}>
              <Text style={[styles.texto, item.rol === 'usuario' && styles.textoUsuario]}>
                {item.texto}
              </Text>
              {item.ticket ? (
                <TouchableOpacity
                  onPress={() => navigation.navigate('TicketDetalle', { id: item.ticket!.id })}
                >
                  <Text style={styles.ticketLink}>Ver {item.ticket.folio} →</Text>
                </TouchableOpacity>
              ) : null}
            </View>
          )}
          ListFooterComponent={
            enviando ? (
              <View style={[styles.burbuja, styles.asistente, styles.escribiendo]}>
                <ActivityIndicator color={colors.blue} size="small" />
              </View>
            ) : null
          }
        />

        <View style={styles.composer}>
          <TextInput
            style={styles.input}
            value={entrada}
            onChangeText={setEntrada}
            placeholder="Describe una falla o haz una pregunta…"
            placeholderTextColor={colors.textLight}
            multiline
            maxLength={2000}
          />
          <TouchableOpacity
            style={[styles.enviar, (!entrada.trim() || enviando) && styles.enviarOff]}
            onPress={enviar}
            disabled={!entrada.trim() || enviando}
          >
            <Text style={styles.enviarTexto}>➤</Text>
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  lista: { flex: 1 },
  listaContenido: { paddingVertical: spacing.md, gap: spacing.md },
  burbuja: {
    maxWidth: '82%',
    padding: spacing.lg,
    borderRadius: 18,
  },
  usuario: {
    alignSelf: 'flex-end',
    backgroundColor: colors.blue,
    borderBottomRightRadius: 4,
  },
  asistente: {
    alignSelf: 'flex-start',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderBottomLeftRadius: 4,
  },
  escribiendo: { paddingVertical: spacing.md },
  texto: { fontSize: 14, color: colors.text, lineHeight: 20 },
  textoUsuario: { color: colors.white },
  ticketLink: {
    marginTop: 6,
    color: colors.blue,
    fontWeight: '700',
    fontSize: 12,
  },
  composer: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingVertical: spacing.md,
    alignItems: 'flex-end',
  },
  input: {
    flex: 1,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.lg,
    paddingTop: 10,
    paddingBottom: 10,
    fontSize: 14,
    color: colors.text,
    maxHeight: 110,
  },
  enviar: {
    backgroundColor: colors.blue,
    borderRadius: radius.full,
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  enviarOff: { opacity: 0.5 },
  enviarTexto: { color: colors.white, fontSize: 16 },
});
