import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useAuth } from '../store/AuthContext';
import { ApiError } from '../api/client';
import { colors, font, radius, spacing } from '../theme/tokens';

export default function LoginScreen() {
  const { iniciarSesion } = useAuth();
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const enviar = async () => {
    setError(null);
    setCargando(true);
    try {
      await iniciarSesion(usuario.trim(), password);
    } catch (e) {
      setError(
        e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.'
      );
    } finally {
      setCargando(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.contenedor}>
        <View style={styles.marca}>
          <View style={styles.marcaTile}>
            <Text style={styles.marcaLetra}>C</Text>
          </View>
          <Text style={styles.marcaNombre}>Crux A.I</Text>
          <Text style={styles.marcaSub}>INDUSTRIAL HUB</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.titulo}>Iniciar sesión</Text>
          <Text style={styles.subtitulo}>
            Accede al centro de mantenimiento de tu organización.
          </Text>

          {error ? <Text style={styles.error}>{error}</Text> : null}

          <Text style={styles.label}>Usuario</Text>
          <TextInput
            style={styles.input}
            value={usuario}
            onChangeText={setUsuario}
            autoCapitalize="none"
            autoComplete="username"
            placeholder="tu.usuario"
            placeholderTextColor={colors.textLight}
          />

          <Text style={styles.label}>Contraseña</Text>
          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            placeholder="••••••••"
            placeholderTextColor={colors.textLight}
          />

          <TouchableOpacity
            style={[styles.boton, cargando && styles.botonDeshabilitado]}
            onPress={enviar}
            disabled={cargando}
          >
            {cargando ? (
              <ActivityIndicator color={colors.white} />
            ) : (
              <Text style={styles.botonTexto}>Entrar</Text>
            )}
          </TouchableOpacity>
        </View>

        <Text style={styles.pie}>CMMS industrial con asistente ORION</Text>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: colors.bg },
  contenedor: {
    flex: 1,
    justifyContent: 'center',
    padding: spacing.xl,
    gap: spacing.xl,
  },
  marca: { alignItems: 'center', gap: 4 },
  marcaTile: {
    width: 56,
    height: 56,
    borderRadius: 14,
    backgroundColor: colors.blue,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.sm,
    shadowColor: colors.blue,
    shadowOpacity: 0.35,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 4,
  },
  marcaLetra: { color: colors.white, fontSize: 26, fontWeight: '800' },
  marcaNombre: {
    fontSize: 22,
    fontWeight: '800',
    color: colors.navy,
    fontFamily: font.extraBold,
    letterSpacing: -0.5,
  },
  marcaSub: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 2.4,
    color: colors.blue,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: spacing.xl,
    gap: spacing.sm,
    shadowColor: colors.text,
    shadowOpacity: 0.08,
    shadowRadius: 24,
    shadowOffset: { width: 0, height: 8 },
    elevation: 5,
  },
  titulo: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.text,
    fontFamily: font.bold,
  },
  subtitulo: {
    fontSize: 13,
    color: colors.textSoft,
    marginBottom: spacing.md,
  },
  error: {
    color: colors.rose,
    fontSize: 13,
    backgroundColor: 'rgba(225,29,72,0.08)',
    borderColor: 'rgba(225,29,72,0.25)',
    borderWidth: 1,
    borderRadius: radius.sm,
    padding: spacing.md,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textMuted,
    marginTop: spacing.sm,
  },
  input: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: 12,
    fontSize: 15,
    color: colors.text,
  },
  boton: {
    backgroundColor: colors.blue,
    borderRadius: radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: spacing.lg,
    shadowColor: colors.blue,
    shadowOpacity: 0.28,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  botonDeshabilitado: { opacity: 0.65 },
  botonTexto: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '700',
  },
  pie: {
    textAlign: 'center',
    fontSize: 12,
    color: colors.textLight,
  },
});
